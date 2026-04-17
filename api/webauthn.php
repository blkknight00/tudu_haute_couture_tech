<?php
/**
 * WebAuthn Registration & Authentication Handler
 *
 * Actions (POST body or GET param):
 *   register_challenge — Generate a challenge for biometric registration (requires session)
 *   register_verify    — Verify attestation and store credential (requires session)
 *   auth_challenge     — Generate a challenge for login given a username
 *   auth_verify        — Verify assertion and create a session
 */

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once '../config.php';
require_once '../cbor.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Helpers ─────────────────────────────────────────────────────────────────

function b64u_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64u_decode(string $data): string {
    $pad = str_repeat('=', (4 - strlen($data) % 4) % 4);
    return base64_decode(strtr($data, '-_', '+/') . $pad);
}

/** Detect the effective Relying Party ID from the current host */
function get_rp_id(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return preg_replace('/:\d+$/', '', $host); // strip port
}

/**
 * Convert a COSE EC2 P-256 public key (map) into a PEM string.
 * COSE keys: 1=kty(2=EC2), 3=alg(-7=ES256), -1=crv(1=P-256), -2=x, -3=y
 */
function cose_ec2_to_pem(array $cose): string {
    $x = $cose[-2] ?? null;
    $y = $cose[-3] ?? null;
    if (!$x || !$y || strlen($x) !== 32 || strlen($y) !== 32) {
        throw new \RuntimeException('Invalid COSE EC2 key coordinates');
    }
    // SubjectPublicKeyInfo DER for P-256 uncompressed point
    $der = hex2bin(
        '3059'               // SEQUENCE (89 bytes)
        . '3013'             // SEQUENCE (19 bytes)
        . '0607'             // OID (7 bytes) — ecPublicKey
        . '2a8648ce3d0201'
        . '0608'             // OID (8 bytes) — P-256
        . '2a8648ce3d030107'
        . '0342'             // BIT STRING (66 bytes)
        . '0004'             // no unused bits + uncompressed point prefix
    ) . $x . $y;

    return "-----BEGIN PUBLIC KEY-----\n"
         . chunk_split(base64_encode($der), 64, "\n")
         . "-----END PUBLIC KEY-----\n";
}

/**
 * Extract COSE public key & credential ID from authData bytes.
 * authData layout: 32 rpIdHash | 1 flags | 4 signCount | 16 aaguid | 2 credIdLen | N credId | CBOR pubKey
 */
function parse_auth_data(string $authData): array {
    if (strlen($authData) < 37) throw new \RuntimeException('authData too short');
    $rpIdHash   = substr($authData, 0, 32);
    $flags      = ord($authData[32]);
    $signCount  = unpack('N', substr($authData, 33, 4))[1];

    $result = ['rpIdHash' => $rpIdHash, 'flags' => $flags, 'signCount' => $signCount,
               'credentialId' => null, 'coseKey' => null];

    // AT flag (bit 6) means attested credential data is present
    if ($flags & 0x40) {
        $aaguid      = substr($authData, 37, 16);
        $credIdLen   = unpack('n', substr($authData, 53, 2))[1];
        $credId      = substr($authData, 55, $credIdLen);
        $cborStart   = 55 + $credIdLen;
        $coseKey     = cbor_decode(substr($authData, $cborStart));

        $result['credentialId'] = $credId;
        $result['coseKey']      = $coseKey;
    }
    return $result;
}

// ── Main dispatcher ──────────────────────────────────────────────────────────

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ── 1. Registration: generate challenge ─────────────────────────────
        case 'register_challenge': {
            if (!isset($_SESSION['logged_in'])) {
                echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
                exit;
            }
            $challenge = random_bytes(32);
            $_SESSION['webauthn_challenge'] = b64u_encode($challenge);
            $_SESSION['webauthn_action']    = 'register';

            $userId   = $_SESSION['usuario_id'];
            $stmt = $pdo->prepare("SELECT username, nombre FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status'  => 'success',
                'options' => [
                    'challenge'              => b64u_encode($challenge),
                    'rp'                     => ['name' => 'TuDu App', 'id' => get_rp_id()],
                    'user'                   => [
                        'id'          => b64u_encode(pack('N', $userId)),
                        'name'        => $user['username'],
                        'displayName' => $user['nombre'],
                    ],
                    'pubKeyCredParams'       => [['type' => 'public-key', 'alg' => -7]],
                    'authenticatorSelection' => [
                        'authenticatorAttachment' => 'platform',
                        'userVerification'        => 'required',
                        'residentKey'             => 'discouraged',
                    ],
                    'timeout'     => 60000,
                    'attestation' => 'none',
                ],
            ]);
            break;
        }

        // ── 2. Registration: verify attestation ─────────────────────────────
        case 'register_verify': {
            if (!isset($_SESSION['logged_in'], $_SESSION['webauthn_challenge'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión inválida']);
                exit;
            }
            $credId         = $input['credentialId']  ?? '';
            $clientDataJSON = b64u_decode($input['clientDataJSON'] ?? '');
            $attestObj      = b64u_decode($input['attestationObject'] ?? '');

            // Verify clientData
            $clientData = json_decode($clientDataJSON, true);
            if (($clientData['type'] ?? '') !== 'webauthn.create') {
                throw new \RuntimeException('Invalid clientData type');
            }
            if (($clientData['challenge'] ?? '') !== $_SESSION['webauthn_challenge']) {
                throw new \RuntimeException('Challenge mismatch');
            }

            // Decode attestationObject (CBOR map: fmt, attStmt, authData)
            $attObj  = cbor_decode($attestObj);
            $authData = $attObj['authData'];

            // Parse authData
            $parsed = parse_auth_data($authData);
            if (!$parsed['credentialId'] || !$parsed['coseKey']) {
                throw new \RuntimeException('No credential data in authData');
            }

            // Convert COSE → PEM
            $pem = cose_ec2_to_pem($parsed['coseKey']);

            // Store in DB
            $userId = $_SESSION['usuario_id'];
            $credentialIdB64 = b64u_encode($parsed['credentialId']);

            // Delete old credential for this user (one per user for simplicity)
            $pdo->prepare("DELETE FROM webauthn_credentials WHERE usuario_id = ?")->execute([$userId]);

            $stmt = $pdo->prepare(
                "INSERT INTO webauthn_credentials (usuario_id, credential_id, public_key, sign_count)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $credentialIdB64, $pem, $parsed['signCount']]);

            unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_action']);

            echo json_encode(['status' => 'success', 'message' => 'Huella registrada correctamente']);
            break;
        }

        // ── 3. Authentication: generate challenge ────────────────────────────
        case 'auth_challenge': {
            $username = trim($input['username'] ?? '');
            if (!$username) {
                echo json_encode(['status' => 'error', 'message' => 'Usuario requerido']);
                exit;
            }

            // Look up user's credential
            $stmt = $pdo->prepare(
                "SELECT u.id, wc.credential_id
                 FROM usuarios u
                 JOIN webauthn_credentials wc ON wc.usuario_id = u.id
                 WHERE u.username = ? OR u.email = ?
                 LIMIT 1"
            );
            $stmt->execute([$username, $username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'No hay huella registrada para este usuario']);
                exit;
            }

            $challenge = random_bytes(32);
            $_SESSION['webauthn_challenge'] = b64u_encode($challenge);
            $_SESSION['webauthn_action']    = 'auth';
            $_SESSION['webauthn_user_id']   = $row['id'];

            echo json_encode([
                'status'  => 'success',
                'options' => [
                    'challenge'        => b64u_encode($challenge),
                    'rpId'             => get_rp_id(),
                    'allowCredentials' => [[
                        'type' => 'public-key',
                        'id'   => $row['credential_id'],
                    ]],
                    'userVerification' => 'required',
                    'timeout'          => 60000,
                ],
            ]);
            break;
        }

        // ── 4. Authentication: verify assertion ──────────────────────────────
        case 'auth_verify': {
            if (!isset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_user_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión de autenticación inválida']);
                exit;
            }

            $clientDataJSON = b64u_decode($input['clientDataJSON']  ?? '');
            $authData       = b64u_decode($input['authenticatorData'] ?? '');
            $signature      = b64u_decode($input['signature']         ?? '');

            // Verify clientData
            $clientData = json_decode($clientDataJSON, true);
            if (($clientData['type'] ?? '') !== 'webauthn.get') {
                throw new \RuntimeException('Invalid clientData type');
            }
            if (($clientData['challenge'] ?? '') !== $_SESSION['webauthn_challenge']) {
                throw new \RuntimeException('Challenge mismatch during auth');
            }

            // Verify rpId hash
            $expectedRpIdHash = hash('sha256', get_rp_id(), true);
            $actualRpIdHash   = substr($authData, 0, 32);
            if (!hash_equals($expectedRpIdHash, $actualRpIdHash)) {
                throw new \RuntimeException('rpId hash mismatch');
            }

            // Verify user verification flag (bit 2)
            $flags = ord($authData[32]);
            if (!($flags & 0x04)) {
                throw new \RuntimeException('User verification not performed');
            }

            // Load stored credential
            $userId = $_SESSION['webauthn_user_id'];
            $stmt = $pdo->prepare("SELECT public_key, sign_count FROM webauthn_credentials WHERE usuario_id = ?");
            $stmt->execute([$userId]);
            $cred = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cred) {
                throw new \RuntimeException('Credential not found');
            }

            // Verify ECDSA signature: signed = authData || sha256(clientDataJSON)
            $signedData = $authData . hash('sha256', $clientDataJSON, true);
            $valid = openssl_verify($signedData, $signature, $cred['public_key'], OPENSSL_ALGO_SHA256);
            if ($valid !== 1) {
                throw new \RuntimeException('Signature verification failed');
            }

            // Update sign count
            $newSignCount = unpack('N', substr($authData, 33, 4))[1];
            $pdo->prepare("UPDATE webauthn_credentials SET sign_count = ? WHERE usuario_id = ?")
                ->execute([$newSignCount, $userId]);

            // Fetch user and create session
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Generate JWT Token
            require_once 'vendor/autoload.php';

            // Determine organization
            $isGlobalAdmin = in_array($user['rol'], ['super_admin', 'admin_global']);
            if ($isGlobalAdmin) {
                $stmt_orgs = $pdo->prepare("SELECT id, nombre, 'admin' as rol_organizacion FROM organizaciones");
                $stmt_orgs->execute();
            } else {
                $stmt_orgs = $pdo->prepare("
                    SELECT o.id, o.nombre, mo.rol_organizacion 
                    FROM organizaciones o
                    JOIN miembros_organizacion mo ON o.id = mo.organizacion_id
                    WHERE mo.usuario_id = ?
                ");
                $stmt_orgs->execute([$userId]);
            }
            $organizaciones = $stmt_orgs->fetchAll(PDO::FETCH_ASSOC);
            $org_login = $organizaciones[0] ?? ['id' => 0, 'nombre' => 'Global', 'rol_organizacion' => 'admin'];
            $rol_org_activo = $org_login['rol_organizacion'] ?? 'miembro';

            $payload = [
                'iat' => time(),
                'exp' => time() + (86400 * 7),
                'user' => [
                    'id' => $user['id'],
                    'nombre' => $user['nombre'],
                    'username' => $user['username'],
                    'email' => $user['email'] ?? '',
                    'rol' => $user['rol'],
                    'foto' => $user['foto_perfil'],
                    'organizacion_id' => $org_login['id'],
                    'organizacion_nombre' => $org_login['nombre'],
                    'rol_organizacion' => $rol_org_activo,
                    'organizations' => $organizaciones
                ]
            ];

            $token = \Firebase\JWT\JWT::encode($payload, JWT_SECRET, 'HS256');

            unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_action'], $_SESSION['webauthn_user_id']);

            echo json_encode([
                'status' => 'success',
                'token'  => $token,
                'edition' => APP_EDITION ?? 'standard',
                'user'   => $payload['user']
            ]);
            break;
        }

        // ── Check if user has a registered credential ────────────────────────
        case 'check': {
            if (!isset($_SESSION['logged_in'])) {
                echo json_encode(['status' => 'success', 'registered' => false]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id FROM webauthn_credentials WHERE usuario_id = ?");
            $stmt->execute([$_SESSION['usuario_id']]);
            echo json_encode(['status' => 'success', 'registered' => (bool)$stmt->fetch()]);
            break;
        }

        // ── Delete registered credential ─────────────────────────────────────
        case 'delete': {
            if (!isset($_SESSION['logged_in'])) {
                echo json_encode(['status' => 'error', 'message' => 'No autorizado']); exit;
            }
            $pdo->prepare("DELETE FROM webauthn_credentials WHERE usuario_id = ?")
                ->execute([$_SESSION['usuario_id']]);
            echo json_encode(['status' => 'success', 'message' => 'Huella eliminada']);
            break;
        }

        default:
            echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida: ' . $action]);
    }

} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

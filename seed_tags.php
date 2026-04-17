<?php
require_once 'config.php';

try {
    echo "<h2>Agregando etiquetas predefinidas...</h2>";

    // Paleta de colores para las nuevas etiquetas
    $color_palette = [
        '#E57373', '#F06292', '#BA68C8', '#9575CD', '#7986CB', '#64B5F6',
        '#4FC3F7', '#4DD0E1', '#4DB6AC', '#81C784', '#AED581', '#DCE775',
        '#FFF176', '#FFD54F', '#FFB74D', '#FF8A65', '#A1887F', '#90A4AE'
    ];

    // Etiquetas predefinidas que queremos agregar
    $default_tags = [
        'Urgente', 'Revisión', 'Cliente', 'Frontend', 'Backend',
        'Bug', 'Marketing', 'Diseño', 'Investigación', 'Importante'
    ];

    $stmt_check = $pdo->prepare("SELECT id FROM etiquetas WHERE nombre = ?");
    $stmt_insert = $pdo->prepare("INSERT INTO etiquetas (nombre, color) VALUES (?, ?)");
    
    $tags_added = 0;
    
    foreach ($default_tags as $tag_name) {
        $stmt_check->execute([$tag_name]);
        if ($stmt_check->rowCount() == 0) {
            $random_color = $color_palette[array_rand($color_palette)];
            $stmt_insert->execute([$tag_name, $random_color]);
            $tags_added++;
            echo "✅ Etiqueta '<strong>{$tag_name}</strong>' agregada.<br>";
        } else {
            echo "ℹ️ Etiqueta '{$tag_name}' ya existía.<br>";
        }
    }
    
    echo "<hr><h3>¡Listo! Se procesaron las etiquetas.</h3>";
    echo "<a href='dashboard.php' style='display:inline-block; padding:10px 20px; background-color:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver al Dashboard</a>";

} catch (PDOException $e) {
    echo "<h1>❌ Error</h1><pre>" . $e->getMessage() . "</pre>";
}
?>
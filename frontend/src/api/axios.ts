import axios from 'axios';

// Determine the base URL dynamically.
// This logic attempts to find the "root" of the application on the server.
// It assumes the 'api' folder is located at the same level as the 'index.html' (or the build output root).

const getBaseUrl = () => {
    const { protocol, hostname, port, pathname } = window.location;

    // Development Mode (Vite dev server on port 5173)
    // BASE_URL still points to Apache for images/assets.
    // axios uses a RELATIVE baseURL so Vite's proxy handles the forwarding.
    if (port === '5173') {
        return `${protocol}//${hostname}/tudu_haute_couture_tech`;
    }

    // Production / Served from PHP
    // Keep the tudu-v2 (or any other base folder) but remove the frontend app paths
    let rootPath = pathname.replace(/(\/frontend\/dist\/.*|\/frontend\/.*|\/kanban|\/calendar|\/resources)$/, '');

    // Explicit Fallback for known production folder
    if (rootPath === '/' || rootPath === '') {
        rootPath = ''; // Usually empty in production if accessed via raw domain
    } else {
        rootPath = rootPath.replace(/\/$/, '');
    }

    const origin = window.location.origin || `${protocol}//${hostname}${port ? ':' + port : ''}`;
    return `${origin}${rootPath}`;
};

export const BASE_URL = getBaseUrl();

// In dev mode: use RELATIVE path so Vite proxy intercepts the request
// In prod mode: use absolute BASE_URL
const getApiBaseUrl = () => {
    if (window.location.port === '5173') {
        return '/tudu_haute_couture_tech/api';
    }
    return `${BASE_URL}/api`;
};

const api = axios.create({
    baseURL: getApiBaseUrl(),
    withCredentials: true,
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('tudu_jwt_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`; // standard
        config.headers['X-Auth-Token'] = token; // bypass apache strip
    }
    return config;
}, (error) => {
    return Promise.reject(error);
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            const requestUrl = error.config?.url || '';
            // Don't redirect on auth endpoints — 401 there is expected when not logged in
            const isAuthEndpoint = requestUrl.includes('auth.php');
            if (!isAuthEndpoint && window.location.hash !== '#/login' && window.location.hash !== '#/register') {
                localStorage.removeItem('tudu_jwt_token');
                window.location.hash = '#/login';
            }
        }
        return Promise.reject(error);
    }
);

export default api;

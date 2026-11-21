import Alpine from 'alpinejs';
import './wishlist.js';

// Exponemos Alpine en window para depuración y para que otros scripts
// puedan registrarse antes del start().
window.Alpine = Alpine;

Alpine.start();

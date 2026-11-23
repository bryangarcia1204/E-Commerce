import Alpine from 'alpinejs';
import './wishlist.js';
import Chart from 'chart.js/auto';

window.Chart = Chart;
// Exponemos Alpine en window para depuración y para que otros scripts
// puedan registrarse antes del start().
window.Alpine = Alpine;

Alpine.start();

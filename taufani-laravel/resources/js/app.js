import './bootstrap';
import Chart from 'chart.js/auto';

// Make Chart.js available globally for Alpine x-init blocks
window.Chart = Chart;

// Re-initialize charts after Livewire SPA navigation
document.addEventListener('livewire:navigated', () => {
    document.querySelectorAll('[data-chart-init]').forEach(el => {
        el._x_dataStack && el._x_dataStack.forEach(d => d.initChart?.());
    });
});

// Alpine is bundled inside Livewire v3 — do NOT import or start it here.
// Importing Alpine separately causes "multiple instances" errors that break
// all wire:click, x-show, x-data, and x-transition directives.

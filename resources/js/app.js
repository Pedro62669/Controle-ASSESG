import { dashboardChart, dashboardCharts } from './charts'

/*
 * O Alpine já vem embarcado no Livewire; aqui apenas registramos os
 * componentes da aplicação no ciclo de inicialização dele.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('dashboardCharts', dashboardCharts)
    window.Alpine.data('dashboardChart', dashboardChart)
})

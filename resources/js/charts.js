import ApexCharts from 'apexcharts'

/**
 * Paleta institucional ASSESG — os mesmos valores do @theme em app.css.
 * Mantida aqui em JS porque o ApexCharts precisa das cores em tempo de execução.
 */
export const palette = {
    primary: '#0B3A5D',
    secondary: '#628B72',
    accent: '#C9B999',
    danger: '#B4593F',
    primarySoft: '#597991',
    accentSoft: '#DACFBA',
    grid: '#E4E9ED',
    text: '#062236',
    muted: '#597991',
}

/**
 * Sequência categórica derivada da identidade ASSESG.
 *
 * São oito matizes distintos o bastante para separar fatias vizinhas — com
 * menos cores, uma pizza de 8 fontes acabava repetindo tons parecidos.
 */
const categorical = [
    palette.primary,   // navy
    palette.secondary, // verde sálvia
    palette.accent,    // areia
    palette.danger,    // terracota
    '#4A7A94',         // azul médio
    '#8C6E54',         // marrom
    '#9DBE8A',         // verde claro
    '#7A8CA0',         // cinza azulado
]

/**
 * Gira a sequência para que cada gráfico comece por uma cor coerente com o
 * que ele mostra, sem perder a separação entre fatias.
 */
function rotate(colors, offset) {
    return [...colors.slice(offset), ...colors.slice(0, offset)]
}

/**
 * Preto ou branco sobre a fatia, conforme a luminância — o percentual precisa
 * ficar legível tanto sobre o navy quanto sobre a areia.
 */
function readableOn(color) {
    const hex = color.replace('#', '')
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
    const channel = (c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)
    const luminance = 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)

    return luminance > 0.45 ? '#062236' : '#FFFFFF'
}

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
})

const compact = new Intl.NumberFormat('pt-BR', {
    notation: 'compact',
    compactDisplay: 'short',
    maximumFractionDigits: 1,
})

/**
 * Respeita a preferência de sistema por menos movimento — e, de quebra,
 * deixa a renderização determinística para capturas e testes visuais.
 */
function prefersReducedMotion() {
    return (
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    )
}

const baseOptions = {
    chart: {
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif',
        toolbar: { show: false },
        animations: { enabled: !prefersReducedMotion(), speed: 350 },
        parentHeightOffset: 0,
    },
    tooltip: {
        y: { formatter: (value) => currency.format(value ?? 0) },
    },
    noData: {
        text: 'Nenhuma movimentação no período selecionado',
        style: { color: palette.muted, fontSize: '13px' },
    },
}

function merge(...objects) {
    return objects.reduce((acc, object) => {
        Object.entries(object ?? {}).forEach(([key, value]) => {
            acc[key] =
                value && typeof value === 'object' && !Array.isArray(value)
                    ? merge(acc[key] ?? {}, value)
                    : value
        })

        return acc
    }, {})
}

/**
 * Barras comparativas: entradas x saídas ao longo do período.
 */
function cashFlowOptions(data) {
    return merge(baseOptions, {
        chart: { type: 'bar', height: 340, stacked: false },
        series: [
            { name: 'Entradas', data: data.income ?? [] },
            { name: 'Saídas', data: data.expense ?? [] },
        ],
        colors: [palette.secondary, palette.danger],
        plotOptions: {
            bar: { borderRadius: 4, columnWidth: '58%', borderRadiusApplication: 'end' },
        },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'right', markers: { radius: 4 } },
        grid: { borderColor: palette.grid, strokeDashArray: 4, padding: { left: 4, right: 4 } },
        xaxis: {
            categories: data.labels ?? [],
            labels: { style: { colors: palette.muted, fontSize: '12px' }, rotateAlways: false, hideOverlappingLabels: true },
            axisBorder: { color: palette.grid },
            axisTicks: { color: palette.grid },
        },
        yaxis: {
            labels: {
                style: { colors: palette.muted, fontSize: '12px' },
                formatter: (value) => compact.format(value ?? 0),
            },
        },
    })
}

/**
 * Pizzas de composição (retido, entradas e saídas).
 */
function donutOptions(data, colors) {
    const slices = colors ?? categorical

    return merge(baseOptions, {
        chart: { type: 'donut', height: 400 },
        series: data.values ?? [],
        labels: data.labels ?? [],
        colors: slices,
        stroke: { width: 2, colors: ['#ffffff'] },
        dataLabels: {
            enabled: true,
            // Abaixo de 5% os rótulos se atropelam; a fatia continua
            // identificada pela legenda e pelo tooltip.
            formatter: (percent) => (Number(percent) < 5 ? '' : `${Number(percent).toFixed(1)}%`),
            style: {
                fontSize: '12px',
                fontWeight: 600,
                colors: slices.map(readableOn),
            },
            dropShadow: { enabled: false },
        },
        legend: {
            position: 'bottom',
            fontSize: '12px',
            labels: { colors: palette.text },
            markers: { radius: 4 },
            itemMargin: { horizontal: 6, vertical: 2 },
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '58%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            color: palette.muted,
                            fontSize: '12px',
                            formatter: (w) =>
                                currency.format(
                                    (w.globals.seriesTotals ?? []).reduce((sum, value) => sum + value, 0),
                                ),
                        },
                        value: {
                            color: palette.text,
                            fontSize: '18px',
                            fontWeight: 600,
                            formatter: (value) => currency.format(Number(value) || 0),
                        },
                    },
                },
            },
        },
    })
}

/**
 * Um donut sem nenhum valor renderiza um anel vazio e confuso; nesse caso
 * deixamos o ApexCharts exibir o estado "sem dados".
 */
function hasData(data) {
    return (data?.values ?? []).some((value) => Number(value) > 0)
}

/**
 * Projeção: barras de entradas e saídas previstas, com o saldo acumulado
 * como linha sobre um segundo eixo.
 */
function projectionOptions(data) {
    return merge(baseOptions, {
        chart: { type: 'line', height: 360, stacked: false },
        series: [
            { name: 'Entradas previstas', type: 'column', data: data.income ?? [] },
            { name: 'Saídas previstas', type: 'column', data: data.expense ?? [] },
            { name: 'Saldo projetado', type: 'line', data: data.balance ?? [] },
        ],
        colors: [palette.secondary, palette.danger, palette.primary],
        plotOptions: {
            bar: { borderRadius: 4, columnWidth: '55%', borderRadiusApplication: 'end' },
        },
        stroke: { width: [0, 0, 3], curve: 'smooth' },
        markers: { size: [0, 0, 4], strokeWidth: 0 },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'right', markers: { radius: 4 } },
        grid: { borderColor: palette.grid, strokeDashArray: 4, padding: { left: 4, right: 4 } },
        xaxis: {
            categories: data.labels ?? [],
            labels: { style: { colors: palette.muted, fontSize: '12px' }, hideOverlappingLabels: true },
            axisBorder: { color: palette.grid },
            axisTicks: { color: palette.grid },
        },
        yaxis: [
            {
                seriesName: 'Entradas previstas',
                labels: {
                    style: { colors: palette.muted, fontSize: '12px' },
                    formatter: (value) => compact.format(value ?? 0),
                },
            },
            { seriesName: 'Entradas previstas', show: false },
            {
                opposite: true,
                seriesName: 'Saldo projetado',
                labels: {
                    style: { colors: palette.primary, fontSize: '12px' },
                    formatter: (value) => compact.format(value ?? 0),
                },
                title: { text: 'Saldo projetado', style: { color: palette.muted, fontSize: '11px', fontWeight: 500 } },
            },
        ],
        noData: { text: 'Nenhuma movimentação recorrente lançada para projetar' },
    })
}

const builders = {
    cashFlow: (data) => cashFlowOptions(data),
    retained: (data) => donutOptions(data, [palette.primary, palette.accent]),
    income: (data) => donutOptions(data, categorical),
    // Saídas começam pelo terracota, a cor que a interface já usa para elas.
    expense: (data) => donutOptions(data, rotate(categorical, 3)),
    projection: (data) => projectionOptions(data),
}

/**
 * Componente Alpine que mantém os quatro gráficos sincronizados com o filtro
 * de período do Livewire, atualizando as séries sem recriar o DOM.
 */
export function dashboardCharts(initialCharts) {
    return {
        charts: {},
        instances: {},

        init() {
            this.charts = initialCharts ?? {}
            this.renderAll()

            window.addEventListener('charts-updated', (event) => {
                this.charts = event.detail?.charts ?? {}
                this.renderAll()
            })

            this.$el.addEventListener('chart-refresh', () => this.renderAll())
        },

        destroy() {
            Object.values(this.instances).forEach((chart) => chart.destroy())
            this.instances = {}
        },

        renderAll() {
            Object.keys(builders).forEach((key) => this.renderChart(key))
        },

        renderChart(key) {
            const element = this.$refs[key]

            if (!element) {
                return
            }

            const data = this.charts[key] ?? {}
            const isSeriesChart = key === 'cashFlow' || key === 'projection'
            const options = builders[key](isSeriesChart || hasData(data) ? data : { labels: [], values: [] })

            if (this.instances[key]) {
                this.instances[key].updateOptions(options, true, true, true)

                return
            }

            this.instances[key] = new ApexCharts(element, options)
            this.instances[key].render()
        },
    }
}

/**
 * Monta um único gráfico, para blocos que ficam fora do container principal.
 *
 * Só o elemento do gráfico entra em wire:ignore, de modo que o restante do
 * card (botões de horizonte, totalizadores) continue reativo no Livewire.
 */
export function dashboardChart(key, initialData) {
    return {
        instance: null,

        init() {
            this.draw(initialData ?? {})

            window.addEventListener('charts-updated', (event) => {
                const data = event.detail?.charts?.[key]

                if (data) {
                    this.draw(data)
                }
            })
        },

        destroy() {
            this.instance?.destroy()
            this.instance = null
        },

        draw(data) {
            const element = this.$refs.chart

            if (!element || !builders[key]) {
                return
            }

            const options = builders[key](data)

            if (this.instance) {
                this.instance.updateOptions(options, true, true, true)

                return
            }

            this.instance = new ApexCharts(element, options)
            this.instance.render()
        },
    }
}

export default dashboardCharts

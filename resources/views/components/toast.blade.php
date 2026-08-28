{{-- Avisos disparados por $this->dispatch('notify', type: ..., message: ...) --}}
<div x-data="{
        toasts: [],
        push(detail) {
            const id = Date.now() + Math.random()
            this.toasts.push({ id, ...detail })
            setTimeout(() => this.remove(id), 4000)
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id)
        },
     }"
     @notify.window="push($event.detail)"
     class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4"
     x-cloak>
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition.opacity.duration.300ms
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 shadow-lg"
             :class="toast.type === 'error'
                 ? 'border-danger-200 bg-danger-50 text-danger-800'
                 : 'border-secondary-200 bg-secondary-50 text-secondary-800'">
            <span x-text="toast.message" class="flex-1 text-sm font-medium"></span>
            <button type="button" @click="remove(toast.id)" class="text-current opacity-60 hover:opacity-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>
</div>

@php
    use App\Validation\TransactionValidation;

    $requiresDescription = $form->requiresDescription();
    $currentDocument = $form->currentDocumentName();
@endphp

<div>
    @if ($open)
        <x-modal :title="$form->isEditing() ? 'Editar movimentação' : 'Nova movimentação'"
                 subtitle="Anexe o comprovante ou justifique a movimentação na descrição."
                 wire:click="close">
            <x-slot:close>
                <button type="button" class="btn-ghost -mt-1 -mr-2 p-2" wire:click="close" aria-label="Fechar">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </x-slot:close>

            <form wire:submit="save" class="space-y-5 px-5 py-5">
                <div>
                    <span class="label">Tipo de movimentação</span>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($types as $type)
                            <label @class([
                                'flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition',
                                'border-secondary-500 bg-secondary-50' => $form->type === $type->value && $type->value === 'income',
                                'border-danger-500 bg-danger-50' => $form->type === $type->value && $type->value === 'expense',
                                'border-primary-200 hover:bg-primary-50' => $form->type !== $type->value,
                            ])>
                                <input type="radio" class="sr-only" wire:model.live="form.type" value="{{ $type->value }}">
                                <span @class([
                                    'flex h-8 w-8 items-center justify-center rounded-full',
                                    'bg-secondary-500 text-white' => $type->value === 'income',
                                    'bg-danger-500 text-white' => $type->value === 'expense',
                                ])>
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="{{ $type->value === 'income' ? 'M12 19.5v-15m0 0-6.75 6.75M12 4.5l6.75 6.75' : 'M12 4.5v15m0 0 6.75-6.75M12 19.5l-6.75-6.75' }}"/>
                                    </svg>
                                </span>
                                <span class="text-sm font-semibold text-primary-800">{{ $type->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('form.type') <p class="error-message">{{ $message }}</p> @enderror
                </div>

                {{-- Fonte: a lista muda conforme o tipo escolhido acima. --}}
                <div>
                    <label for="source" class="label">
                        {{ $form->isExpense() ? 'Destino da saída' : 'Origem da entrada' }}
                        <span class="text-danger-600">*</span>
                    </label>

                    <select id="source" class="input @error('form.source') input-error @enderror"
                            wire:model.live="form.source">
                        <option value="">Selecione a fonte…</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->value }}">{{ $source->label() }}</option>
                        @endforeach
                    </select>

                    @error('form.source')
                        <p class="error-message">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-xs text-primary-400">
                            É por esta informação que o dashboard agrupa entradas e saídas.
                        </p>
                    @enderror
                </div>

                {{-- Classificação: vale para entradas e saídas. --}}
                @php
                    $currentType = \App\Enums\TransactionType::tryFrom($form->type) ?? \App\Enums\TransactionType::Income;
                @endphp

                <div>
                    <span class="label">Classificação <span class="text-danger-600">*</span></span>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($classifications as $classification)
                            <label @class([
                                'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition',
                                'border-primary-500 bg-primary-50' => $form->classification === $classification->value,
                                'border-primary-200 hover:bg-primary-50' => $form->classification !== $classification->value,
                            ])>
                                <input type="radio" class="mt-1 border-primary-300 text-primary-500 focus:ring-primary-500/30"
                                       wire:model.live="form.classification"
                                       value="{{ $classification->value }}">
                                <span>
                                    <span class="block text-sm font-semibold text-primary-800">
                                        {{ $classification->labelFor($currentType) }}
                                    </span>
                                    <span class="block text-xs text-primary-400">
                                        {{ $classification->descriptionFor($currentType) }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('form.classification') <p class="error-message">{{ $message }}</p> @enderror
                </div>

                {{-- Recorrência: aparece em qualquer movimentação recorrente. --}}
                @if ($form->isRecurring())
                    <div class="space-y-4 rounded-lg border border-primary-200 bg-primary-50/60 p-4">
                        <div>
                            <label for="recurrence_interval" class="label">
                                Com que frequência se repete? <span class="text-danger-600">*</span>
                            </label>

                            <select id="recurrence_interval"
                                    class="input @error('form.recurrence_interval') input-error @enderror"
                                    wire:model.live="form.recurrence_interval">
                                <option value="">Selecione o intervalo…</option>
                                @foreach ($intervals as $interval)
                                    <option value="{{ $interval->value }}">
                                        {{ $interval->label() }} — {{ $interval->description() }}
                                    </option>
                                @endforeach
                            </select>

                            @error('form.recurrence_interval') <p class="error-message">{{ $message }}</p> @enderror
                        </div>

                        {{-- Meses escolhidos manualmente. --}}
                        @if ($form->needsMonthSelection())
                            <div>
                                <span class="label">Em quais meses ocorre? <span class="text-danger-600">*</span></span>

                                <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                    @foreach ($months as $number => $monthName)
                                        <button type="button"
                                                wire:click="toggleRecurrenceMonth({{ $number }})"
                                                @class([
                                                    'rounded-md border px-2 py-1.5 text-xs font-semibold transition',
                                                    'border-primary-500 bg-primary-500 text-white' => $form->hasMonth($number),
                                                    'border-primary-200 bg-white text-primary-600 hover:bg-primary-50' => ! $form->hasMonth($number),
                                                ])>
                                            {{ $monthName }}
                                        </button>
                                    @endforeach
                                </div>

                                @error('form.recurrence_months') <p class="error-message">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div>
                            <span class="label">Por quanto tempo? <span class="text-danger-600">*</span></span>

                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($durations as $duration)
                                    <label @class([
                                        'flex cursor-pointer items-start gap-3 rounded-lg border bg-white p-3 transition',
                                        'border-primary-500' => $form->recurrence_duration === $duration->value,
                                        'border-primary-200 hover:bg-primary-50' => $form->recurrence_duration !== $duration->value,
                                    ])>
                                        <input type="radio" class="mt-1 border-primary-300 text-primary-500 focus:ring-primary-500/30"
                                               wire:model.live="form.recurrence_duration"
                                               value="{{ $duration->value }}">
                                        <span>
                                            <span class="block text-sm font-semibold text-primary-800">{{ $duration->label() }}</span>
                                            <span class="block text-xs text-primary-400">{{ $duration->description() }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            @error('form.recurrence_duration') <p class="error-message">{{ $message }}</p> @enderror
                        </div>

                        @if ($form->needsInstallmentCount())
                            <div class="sm:max-w-xs">
                                <label for="recurrence_count" class="label">
                                    Número de parcelas <span class="text-danger-600">*</span>
                                </label>
                                <input id="recurrence_count" type="number" min="2" max="360" step="1"
                                       class="input @error('form.recurrence_count') input-error @enderror"
                                       wire:model.live.debounce.400ms="form.recurrence_count">
                                @error('form.recurrence_count') <p class="error-message">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        {{-- Cronograma calculado, para o usuário conferir antes de salvar. --}}
                        @if ($schedule !== [])
                            <div class="rounded-lg border border-primary-100 bg-white p-3">
                                <p class="text-xs font-semibold tracking-wide text-primary-400 uppercase">
                                    {{ $scheduleTitle }}
                                </p>
                                <p class="mt-1 text-sm font-medium text-primary-800">{{ $schedulePreview }}</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($schedule as $occurrence)
                                        <span class="badge bg-primary-50 text-primary-600">{{ $occurrence }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="amount" class="label">Valor <span class="text-danger-600">*</span></label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-primary-400">R$</span>
                            <input id="amount" type="text" inputmode="decimal" placeholder="0,00"
                                   class="input pl-10 @error('form.amount') input-error @enderror"
                                   wire:model.blur="form.amount">
                        </div>
                        @error('form.amount') <p class="error-message">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="transaction_date" class="label">Data <span class="text-danger-600">*</span></label>
                        <input id="transaction_date" type="date" max="{{ now()->toDateString() }}"
                               class="input @error('form.transaction_date') input-error @enderror"
                               wire:model.blur="form.transaction_date">
                        @error('form.transaction_date') <p class="error-message">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Upload do comprovante: opcional, mas define a obrigatoriedade da descrição. --}}
                <div>
                    <span class="label">Comprovante <span class="font-normal text-primary-400">(PDF, JPG ou PNG · até 5 MB)</span></span>

                    @if ($currentDocument)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-secondary-200 bg-secondary-50 px-3 py-2.5">
                            <div class="flex min-w-0 items-center gap-2">
                                <svg class="h-5 w-5 shrink-0 text-secondary-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                </svg>
                                <span class="truncate text-sm font-medium text-secondary-800">{{ $currentDocument }}</span>
                            </div>

                            <button type="button"
                                    class="text-sm font-medium text-danger-600 hover:text-danger-800"
                                    wire:click="{{ $form->document_path ? 'clearUpload' : 'removeStoredDocument' }}">
                                Remover
                            </button>
                        </div>
                    @else
                        <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-primary-200 px-4 py-6 text-center transition hover:border-primary-400 hover:bg-primary-50">
                            <input type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png" wire:model="form.document_path">
                            <svg class="h-6 w-6 text-primary-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z"/>
                            </svg>
                            <span class="text-sm font-medium text-primary-700">Clique para anexar o comprovante</span>
                            <span class="text-xs text-primary-400">Sem comprovante, a descrição passa a ser obrigatória</span>

                            <span wire:loading wire:target="form.document_path" class="text-xs font-medium text-primary-500">
                                Enviando arquivo…
                            </span>
                        </label>
                    @endif

                    @error('form.document_path') <p class="error-message">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="label">
                        Descrição
                        @if ($requiresDescription)
                            <span class="text-danger-600">*</span>
                        @else
                            <span class="font-normal text-primary-400">(opcional)</span>
                        @endif
                    </label>

                    <textarea id="description" rows="3"
                              class="input @error('form.description') input-error @enderror"
                              placeholder="{{ $requiresDescription
                                  ? 'Justifique a origem ou o destino do dinheiro (mínimo de '.TransactionValidation::MIN_DESCRIPTION_LENGTH.' caracteres).'
                                  : 'Complemente a informação do comprovante, se necessário.' }}"
                              wire:model.blur="form.description"></textarea>

                    <div class="mt-1.5 flex items-start justify-between gap-3">
                        @error('form.description')
                            <p class="error-message">{{ $message }}</p>
                        @else
                            <p class="text-xs text-primary-400">
                                @if ($requiresDescription)
                                    Como não há comprovante anexado, justifique a movimentação em pelo menos
                                    {{ TransactionValidation::MIN_DESCRIPTION_LENGTH }} caracteres.
                                @else
                                    O comprovante anexado já documenta esta movimentação.
                                @endif
                            </p>
                        @enderror

                        <span class="shrink-0 text-xs tabular-nums text-primary-300">
                            {{ mb_strlen((string) $form->description) }}/1000
                        </span>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-primary-100 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-outline" wire:click="close">Cancelar</button>

                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,form.document_path">
                        <span wire:loading.remove wire:target="save">
                            {{ $form->isEditing() ? 'Salvar alterações' : 'Registrar movimentação' }}
                        </span>
                        <span wire:loading wire:target="save">Salvando…</span>
                    </button>
                </div>
            </form>
        </x-modal>
    @endif
</div>

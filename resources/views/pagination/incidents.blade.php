<nav class="incidents-pagination" role="navigation" aria-label="{{ __('pagination.navigation') }}">
    <p class="pagination-summary" role="status" aria-live="polite" aria-atomic="true">
        {{ $paginator->total() > 0
            ? __('pagination.summary', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()])
            : __('pagination.empty') }}
    </p>

    @if ($paginator->hasPages())
        <div class="pagination-controls">
            <button type="button" class="pagination-button"
                wire:click="previousPage('{{ $paginator->getPageName() }}')"
                wire:loading.attr="disabled" @disabled($paginator->onFirstPage())>
                <span aria-hidden="true">‹</span> {{ __('pagination.previous') }}
            </button>

            <span class="pagination-position">
                {{ __('pagination.position', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
            </span>

            <div class="pagination-pages">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pagination-ellipsis" aria-hidden="true">{{ $element }}</span>
                    @else
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span wire:key="pagination-{{ $paginator->getPageName() }}-{{ $page }}"
                                    class="pagination-button is-current" aria-current="page"
                                    aria-label="{{ __('pagination.current_page', ['page' => $page]) }}">{{ $page }}</span>
                            @else
                                <button wire:key="pagination-{{ $paginator->getPageName() }}-{{ $page }}"
                                    type="button" class="pagination-button"
                                    wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                    wire:loading.attr="disabled"
                                    aria-label="{{ __('pagination.go_to_page', ['page' => $page]) }}">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            <button type="button" class="pagination-button"
                wire:click="nextPage('{{ $paginator->getPageName() }}')"
                wire:loading.attr="disabled" @disabled(! $paginator->hasMorePages())>
                {{ __('pagination.next') }} <span aria-hidden="true">›</span>
            </button>
        </div>
    @endif
</nav>

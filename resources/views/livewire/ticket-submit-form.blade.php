<div class="h-[calc(100vh-2rem)] w-full flex flex-col relative bg-white" x-data="{ showMobileDetails: false }">
    {{-- Flash Messages --}}
    @if (session()->has('success') || session()->has('error'))
        <div class="absolute top-4 left-1/2 transform -translate-x-1/2 z-50 w-full max-w-md px-4 pointer-events-none">
            <div class="{{ session()->has('success') ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200' }} border shadow-lg rounded-lg p-4 flex items-center justify-between animate-in slide-in-from-top-4 fade-in duration-300 pointer-events-auto">
                <span class="text-sm font-medium">{{ session('success') ?? session('error') }}</span>
                <button type="button" onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    @endif

    @if($selectedTicket)
        {{-- VIEW: Single Ticket --}}
        <div class="flex flex-1 w-full h-full overflow-hidden" wire:key="view-ticket-{{ $selectedTicket->id }}">
            
            {{-- Mobile Overlay --}}
            <div 
                class="fixed inset-0 z-40 bg-black/40 bg-opacity-75 lg:hidden" 
                x-show="showMobileDetails" 
                x-transition.opacity 
                @click="showMobileDetails = false"
                style="display: none;"
            ></div>

            {{-- Sidebar --}}
            <div 
                class="fixed inset-y-0 left-0 z-50 w-80 bg-white shadow-xl transform transition-transform duration-300 ease-in-out lg:static lg:shadow-none lg:translate-x-0 lg:border-r lg:border-gray-200 lg:flex lg:flex-col lg:z-auto"
                :class="showMobileDetails ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            >
                <div class="flex-none px-4 py-3 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <h3 class="font-bold text-gray-900">{{ __('creators-ticketing::resources.frontend.ticket_details') }}</h3>
                    <button @click="showMobileDetails = false" class="text-gray-500 hover:text-gray-700 lg:hidden">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-8">
                    {{-- Status --}}
                    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 block">
                            {{ __('creators-ticketing::resources.frontend.status_label') }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-opacity-10 border" style="background-color: {{ $selectedTicket->status->color }}10; color: {{ $selectedTicket->status->color }}; border-color: {{ $selectedTicket->status->color }}20;">
                            <span class="w-2 h-2 rounded-full mr-2" style="background-color: {{ $selectedTicket->status->color }}"></span>
                            {{ $selectedTicket->status->name }}
                        </span>
                    </div>

                    {{-- Info --}}
                    <div>
                        <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">
                            {{ __('creators-ticketing::resources.frontend.ticket_information') }}
                        </h4>
                        <dl class="space-y-4 text-sm">
                            <div class="flex justify-between items-center">
                                <dt class="text-gray-500">{{ __('creators-ticketing::resources.frontend.created_at_label') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $selectedTicket->created_at->format('M d, Y') }}</dd>
                            </div>
                            <div class="flex justify-between items-center">
                                <dt class="text-gray-500">{{ __('creators-ticketing::resources.frontend.last_activity_label') }}</dt>
                                <dd class="font-medium text-gray-900">
                                    {{ $selectedTicket->last_activity_at ? \Carbon\Carbon::parse($selectedTicket->last_activity_at)->diffForHumans() : __('creators-ticketing::resources.frontend.just_now') }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Custom Data --}}
                    @if(!empty($selectedTicket->custom_fields))
                        <div>
                            <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">
                                {{ __('creators-ticketing::resources.frontend.form_data_label') }}
                            </h4>
                            <dl class="space-y-5">
                                @foreach($selectedTicket->custom_fields as $key => $value)
                                    @continue(empty($value))
                                    <div>
                                        <dt class="text-xs text-gray-500 uppercase tracking-wide mb-1.5">{{ str_replace('_', ' ', $key) }}</dt>
                                        <dd class="text-sm font-medium text-gray-900 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200 break-words">
                                            @php
                                                $isFileValue = is_array($value) && (empty($value) || (is_string(head($value)) && str_contains(head($value), 'ticket-attachments/')));
                                            @endphp

                                            @if($isFileValue)
                                              @include('creators-ticketing::livewire.ticket-attachments-display', [
                                                    'ticketId' => $selectedTicket->id,
                                                    'files' => is_array($value) ? $value : [$value],
                                                    'label' => null,
                                                ])
                                            @elseif(is_array($value))
                                                <ul class="list-none space-y-1">
                                                    @foreach($value as $item)
                                                        <li>{{ is_string($item) ? $item : json_encode($item) }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Main Chat Area --}}
            <div class="flex-1 flex flex-col min-w-0 bg-white relative h-full">
                <div class="h-16 flex-none bg-white border-b border-gray-200 flex items-center justify-between px-4 lg:px-6 z-20 shadow-sm">
                    <div class="flex items-center gap-3 min-w-0">
                        <button wire:click="backToList" class="p-2 -ml-2 text-gray-500 hover:bg-gray-100 rounded-full transition-colors focus:outline-none" title="{{ __('creators-ticketing::resources.frontend.back_to_list') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </button>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-gray-900 truncate">{{ $selectedTicket->title }}</h2>
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <span class="font-mono">#{{ $selectedTicket->ticket_uid }}</span>
                            </div>
                        </div>
                    </div>

                    <button @click="showMobileDetails = !showMobileDetails" class="lg:hidden p-2 text-gray-500 hover:bg-gray-100 rounded-full focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                </div>

                <div class="flex-1 relative flex flex-col overflow-hidden bg-slate-50">
                    <div class="w-full h-full">
                        @livewire('creators-ticketing.public-ticket-chat', ['ticketId' => $selectedTicket->id], key('chat-'.$selectedTicket->id))
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- VIEW: List & Submit Form --}}
        <div class="flex flex-col h-full overflow-hidden" wire:key="list-main-view">
            {{-- Main Scroll Area --}}
            <div class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-8">
                <div class="max-w-4xl mx-auto w-full">
                    
                    {{-- Tabs --}}
                    <div class="mb-6">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <button 
                                    wire:click="showNewTicketForm"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-sm transition {{ $activeTab === 'new' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                                >
                                    <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    {{ __('creators-ticketing::resources.frontend.new_ticket') }}
                                </button>
                                
                                @if(auth()->check())
                                    <button 
                                        wire:click="showList"
                                        class="whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-sm transition {{ $activeTab === 'list' || $activeTab === 'view' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                                    >
                                        <svg class="w-5 h-5 inline-block mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                        {{ __('creators-ticketing::resources.frontend.my_tickets') }}
                                        @if(count($userTickets) > 0)
                                            <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-bold rounded-full">{{ count($userTickets) }}</span>
                                        @endif
                                    </button>
                                @endif
                            </nav>
                        </div>
                    </div>

                    {{-- Main Card --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 min-h-[500px]">
                        <div class="p-6 sm:p-8">
                            @if($showForm)
                                <div wire:key="submit-form-container">
                                    <div class="text-center mb-10">
                                        <h3 class="text-2xl font-bold text-gray-900 tracking-tight">{{ __('creators-ticketing::resources.frontend.submit_ticket_title') }}</h3>
                                        <p class="mt-2 text-sm text-gray-500">{{ __('creators-ticketing::resources.frontend.submit_ticket_desc') }}</p>
                                    </div>

                                    <form wire:submit.prevent="submit" class="max-w-4xl mx-auto">
                                        <div class="grid grid-cols-1 gap-6">
                                            
                                            {{-- Subject --}}
                                            <div>
                                                <label class="block text-sm font-semibold text-gray-900 mb-2">
                                                    {{ __('creators-ticketing::resources.frontend.subject_label') }} <span class="text-red-500">*</span>
                                                </label>
                                                <input 
                                                    type="text" 
                                                    wire:model="subject" 
                                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2.5 transition-shadow"
                                                    placeholder="{{ __('creators-ticketing::resources.frontend.subject_placeholder') }}"
                                                >
                                                @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                            </div>

                                            {{-- Description --}}
                                            <div>
                                                <label class="block text-sm font-semibold text-gray-900 mb-2">
                                                    {{ __('creators-ticketing::resources.frontend.description_label') }} <span class="text-red-500">*</span>
                                                </label>
                                                <textarea 
                                                    wire:model="description" 
                                                    rows="6" 
                                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-3 resize-y transition-shadow"
                                                    placeholder="{{ __('creators-ticketing::resources.frontend.description_placeholder') }}"
                                                ></textarea>
                                                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                            </div>

                                            {{-- Submit Button --}}
                                            <div class="pt-4">
                                                <button type="submit"
                                                    wire:loading.attr="disabled"
                                                    wire:target="submit"
                                                    class="w-full flex justify-center items-center py-3.5 px-6 border border-transparent rounded-xl shadow-md text-sm font-bold text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 transform active:scale-[0.99]">

                                                    <span class="block w-full text-center"
                                                        wire:loading.remove
                                                        wire:target="submit">
                                                        {{ __('creators-ticketing::resources.frontend.submit_btn') }}
                                                    </span>

                                                    <span class="block w-full text-center"
                                                        wire:loading
                                                        wire:target="submit">
                                                        <div class="flex items-center justify-center gap-2 w-full">
                                                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                                        stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor"
                                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 
                                                                    5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 
                                                                    3 7.938l3-2.647z"></path>
                                                            </svg>
                                                            {{ __('creators-ticketing::resources.frontend.submitting_btn') }}
                                                        </div>
                                                    </span>

                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            @else
                                {{-- Ticket List View --}}
                                <div wire:key="ticket-list-container">
                                    @if(count($userTickets) > 0)
                                        <div class="overflow-hidden bg-white border border-gray-200 rounded-xl shadow-sm">
                                            <ul class="divide-y divide-gray-100">
                                                @foreach($userTickets as $ticket)
                                                    <li wire:click="viewTicket({{ $ticket->id }})" class="hover:bg-gray-50 transition duration-150 ease-in-out cursor-pointer group">
                                                        <div class="px-5 py-5">
                                                            <div class="flex items-center justify-between mb-2">
                                                                <div class="flex items-center gap-3 min-w-0">
                                                                    <p class="text-sm font-bold text-gray-900 group-hover:text-blue-600 truncate transition-colors">{{ $ticket->title }}</p>
                                                                    @if($ticket->publicReplies->where('is_seen', false)->where('user_id', '!=', auth()->id())->count() > 0)
                                                                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-800 animate-pulse">
                                                                            {{ __('creators-ticketing::resources.frontend.new_reply') }}
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                <div class="flex-shrink-0 ml-2">
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border" style="background-color: {{ $ticket->status->color }}10; color: {{ $ticket->status->color }}; border-color: {{ $ticket->status->color }}20;">
                                                                        {{ $ticket->status->name }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="flex items-center justify-between text-xs text-gray-500">
                                                                <div class="flex items-center gap-2">
                                                                    <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">#{{ $ticket->ticket_uid }}</span>
                                                                </div>
                                                                <div class="flex items-center gap-1">
                                                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                                    <span>{{ $ticket->last_activity_at ? \Carbon\Carbon::parse($ticket->last_activity_at)->diffForHumans() : $ticket->created_at->diffForHumans() }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @else
                                        <div class="text-center py-20">
                                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-50 mb-6 border border-gray-100">
                                                <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                            </div>
                                            <h3 class="text-lg font-bold text-gray-900">{{ __('creators-ticketing::resources.frontend.no_tickets_title') }}</h3>
                                            <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">{{ __('creators-ticketing::resources.frontend.no_tickets_desc') }}</p>
                                            <div class="mt-8">
                                                <button wire:click="$set('showForm', true)" type="button" class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    {{ __('creators-ticketing::resources.frontend.create_new_btn') }}
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
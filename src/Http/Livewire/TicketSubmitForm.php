<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use daacreators\CreatorsTicketing\Events\TicketCreated;
use daacreators\CreatorsTicketing\Support\TicketFileHelper;

class TicketSubmitForm extends Component
{
    use WithFileUploads;

    #[Url(as: 'tab', except: 'new')] 
    public $activeTab = 'new';

    #[Url(as: 'ticket', except: '')]
    public $urlTicketId = '';

    public $subject = '';
    public $description = '';
    public $custom_fields = [];
    public $userTickets = [];
    public $showForm = true; 
    public $selectedTicket = null; 

    public function mount()
    {
        $this->loadUserTickets();

        if ($this->urlTicketId) {
            $this->viewTicket($this->urlTicketId);
        } elseif ($this->activeTab === 'list') {
            $this->showList();
        } else {
            $this->showNewTicketForm();
        }
    }

    protected function loadUserTickets()
    {
        if (auth()->check()) {
            $this->userTickets = Ticket::where('user_id', auth()->id())
                ->with(['status', 'publicReplies'])
                ->orderBy('last_activity_at', 'desc')
                ->get();
        }
    }

    public function showNewTicketForm()
    {
        $this->activeTab = 'new';
        $this->urlTicketId = '';
        $this->showForm = true;
        $this->selectedTicket = null;
    }

    public function showList()
    {
        $this->activeTab = 'list';
        $this->urlTicketId = '';
        $this->showForm = false;
        $this->selectedTicket = null;
    }

     public function viewTicket($ticketId)
    {
        $this->selectedTicket = Ticket::with(['status', 'publicReplies.user'])
            ->where('id', $ticketId)
            ->where('user_id', auth()->id())
            ->first();

        if ($this->selectedTicket) {
             $this->activeTab = 'view';
             $this->urlTicketId = $ticketId;
             $this->showForm = false;

             foreach ($this->selectedTicket->publicReplies as $r) {
                 $r->markSeenBy(auth()->id());
             }
             $this->selectedTicket->markSeenBy(auth()->id());
        } else {
            $this->backToList();
        }
    }
    
    public function backToList()
    {
        $this->loadUserTickets();
        $this->showList();
    }

    public function getRules()
    {
        return [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
        ];
    }

    public function validationAttributes()
    {
        return [
            'subject' => __('creators-ticketing::resources.frontend.subject_label'),
            'description' => __('creators-ticketing::resources.frontend.description_label'),
        ];
    }

    public function submit()
    {
        $maxTickets = config('creators-ticketing.max_open_tickets_per_user');
            
        if ($maxTickets && $maxTickets > 0) {
            $openTicketsCount = Ticket::where('user_id', auth()->id())
                ->whereHas('status', fn($q) => $q->where('is_closing_status', false))
                ->count();
            
            if ($openTicketsCount >= $maxTickets) {
                session()->flash('error', config('creators-ticketing.ticket_limit_message'));
                return;
            }
        }

        $this->validate();

        $defaultStatus = TicketStatus::where('is_default_for_new', true)->first();

        $ticket = Ticket::create([
            'custom_fields' => [
                'subject' => $this->subject,
                'description' => $this->description,
            ],
            'user_id' => auth()->id(),
            'ticket_status_id' => $defaultStatus?->id,
            'last_activity_at' => now(),
        ]);

        event(new TicketCreated($ticket, auth()->user()));

        session()->flash('success', 'Ticket submitted successfully!');

        $this->reset(['subject', 'description', 'custom_fields']);
        $this->showList(); 
        $this->loadUserTickets();
    }

    public function render()
    {
        return view('creators-ticketing::livewire.ticket-submit-form');
    }
}
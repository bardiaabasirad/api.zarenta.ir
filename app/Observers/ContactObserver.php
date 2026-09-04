<?php

namespace App\Observers;

use App\Events\SettingsChanged;
use App\Models\Contact;

class ContactObserver
{
    public function created(Contact $contact): void
    {
        event(new SettingsChanged());
    }

    public function updated(Contact $contact): void
    {
        event(new SettingsChanged());
    }

    public function deleted(Contact $contact): void
    {
        event(new SettingsChanged());
    }
}

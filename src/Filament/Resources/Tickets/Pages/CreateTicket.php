<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages;

use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $customFields = $record->custom_fields ?? [];
        $hasChanges = false;
        $disk = Storage::disk('private');

        foreach ($customFields as $key => $value) {
            if (is_array($value)) {
                $newPaths = [];
                $filesMoved = false;

                foreach ($value as $filePath) {
                    if (is_string($filePath) && str_contains($filePath, 'ticket-attachments/temp/')) {
                        $filename = basename($filePath);
                        $newPath = "ticket-attachments/{$record->id}/{$filename}";

                        if ($disk->exists($filePath)) {
                            $disk->move($filePath, $newPath);
                            $newPaths[] = $newPath;
                            $filesMoved = true;
                        } else {
                            $newPaths[] = $filePath;
                        }
                    } else {
                        $newPaths[] = $filePath;
                    }
                }

                if ($filesMoved) {
                    $customFields[$key] = $newPaths;
                    $hasChanges = true;
                }
            } elseif (is_string($value) && str_contains($value, 'ticket-attachments/temp/')) {
                $filename = basename($value);
                $newPath = "ticket-attachments/{$record->id}/{$filename}";

                if ($disk->exists($value)) {
                    $disk->move($value, $newPath);
                    $customFields[$key] = $newPath;
                    $hasChanges = true;
                }
            }
        }

        if ($hasChanges) {
            $record->update(['custom_fields' => $customFields]);
        }
    }
}
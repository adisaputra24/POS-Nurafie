<?php

namespace App\Filament\Resources\StokResource\Pages;

use App\Filament\Resources\StokResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Stok;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateStok extends CreateRecord
{
    protected static string $resource = StokResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['id_pemilik'] = Filament::auth()->id();

        // Konversi jumlah stok menjadi negatif jika jenis_stok adalah 'Out'
        if ($data['jenis_stok'] === 'Out') {
            // Validasi apakah stok tersedia mencukupi
            $currentStock = Stok::where('id_produk', $data['id_produk'])
                ->get()
                ->sum(function ($stok) {
                    if ($stok->jenis_stok === 'In') {
                        return $stok->jumlah_stok;
                    } else {
                        return -$stok->jumlah_stok;
                    }
                });

            if ($currentStock < $data['jumlah_stok']) {
                $this->halt();
                Notification::make()
                    ->title('Stok tidak mencukupi')
                    ->body("Stok tersedia hanya {$currentStock}")
                    ->danger()
                    ->send();

                return $data;
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

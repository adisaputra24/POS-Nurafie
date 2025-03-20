<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StokResource\Pages;
use App\Models\Stok;
use App\Models\Produk;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Facades\Filament;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Split;
use Illuminate\Database\Eloquent\Builder;

class StokResource extends Resource
{
    protected static ?string $model = Stok::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $label = 'Stok Produk';
    protected static ?string $pluralLabel = 'Stok Produk';
    protected static ?string $navigationLabel = 'Stok Produk';
    protected static ?string $navigationGroup = 'Inventory';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('id_produk')
                    ->label('Produk')
                    ->options(function () {
                        return Produk::where('id_pemilik', Filament::auth()->id())
                            ->pluck('nama_produk', 'id_produk');
                    })
                    ->searchable()
                    ->required(),
                Select::make('jenis_stok')
                    ->label('Jenis Stok')
                    ->options([
                        'In' => 'Masuk (In)',
                        'Out' => 'Keluar (Out)',
                    ])
                    ->required(),
                TextInput::make('jumlah_stok')
                    ->label('Jumlah Stok')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                Select::make('jenis_transaksi')
                    ->label('Jenis Transaksi')
                    ->options([
                        'Pembelian' => 'Pembelian',
                        'Penjualan' => 'Penjualan',
                        'Retur' => 'Retur',
                        'Penyesuaian' => 'Penyesuaian',
                    ])
                    ->required(),
                DatePicker::make('tanggal_stok')
                    ->label('Tanggal')
                    ->default(now())
                    ->required(),
                Hidden::make('id_pemilik')
                    ->default(fn() => Filament::auth()->id())
                    ->dehydrated(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function () {
                // Get unique products with calculated totals
                return Produk::query()
                    ->where('id_pemilik', Filament::auth()->id())
                    ->select('produks.*')
                    ->addSelect([
                        'last_updated' => Stok::select('updated_at')
                            ->whereColumn('produks.id_produk', 'stoks.id_produk')
                            ->latest()
                            ->limit(1)
                    ]);
            })
            ->columns([
                TextColumn::make('nama_produk')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stok_tersedia')
                    ->label('Stok Tersedia')
                    ->getStateUsing(function ($record) {
                        // Calculate total stock for this product
                        $totalStok = Stok::where('id_produk', $record->id_produk)
                            ->get()
                            ->sum(function ($stok) {
                                if ($stok->jenis_stok === 'In') {
                                    return $stok->jumlah_stok;
                                } else {
                                    return -$stok->jumlah_stok;
                                }
                            });
                        return $totalStok;
                    })
                    ->sortable(),
                TextColumn::make('harga')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('last_updated')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detail Stok')
                    ->schema([
                        Split::make([
                            Grid::make(2)
                                ->schema([
                                    Group::make([
                                        TextEntry::make('produk.nama_produk')
                                            ->label('Nama Produk'),
                                        TextEntry::make('jumlah_stok')
                                            ->label('Jumlah Stok')
                                            ->suffix(fn($record) => $record->jenis_stok === 'In' ? ' (Masuk)' : ' (Keluar)'),
                                        TextEntry::make('jenis_transaksi')
                                            ->label('Jenis Transaksi'),
                                        TextEntry::make('tanggal_stok')
                                            ->label('Tanggal')
                                            ->date(),
                                    ]),
                                    Group::make([
                                        TextEntry::make('stok_tersedia')
                                            ->label('Total Stok Tersedia')
                                            ->getStateUsing(function ($record) {
                                                // Hitung total stok produk
                                                $totalStok = Stok::where('id_produk', $record->id_produk)
                                                    ->get()
                                                    ->sum(function ($stok) {
                                                        if ($stok->jenis_stok === 'In') {
                                                            return $stok->jumlah_stok;
                                                        } else {
                                                            return -$stok->jumlah_stok;
                                                        }
                                                    });
                                                return $totalStok;
                                            }),
                                        TextEntry::make('created_at')
                                            ->label('Dibuat pada')
                                            ->dateTime(),
                                        TextEntry::make('updated_at')
                                            ->label('Diperbarui pada')
                                            ->dateTime(),
                                    ]),
                                ]),
                        ])->from('lg'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStoks::route('/'),
            'create' => Pages\CreateStok::route('/create'),
            // 'view' => Pages\ViewStok::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Menggunakan relasi dengan produk untuk mendapatkan id_pemilik
        return parent::getEloquentQuery()
            ->join('produks', 'stoks.id_produk', '=', 'produks.id_produk')
            ->where('produks.id_pemilik', Filament::auth()->id())
            ->distinct('stoks.id_produk')
            ->select('stoks.*');
    }
}

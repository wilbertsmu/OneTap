<?php

namespace App\Filament\Pages;

use App\Filament\Exports\EmployeeAbsenceExporter;
use App\Filament\Exports\StudentAbsenceExporter;
use App\Filament\Resources\AttendanceLogResource;
use App\Models\Employee;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shows students/employees who have no successful entry log within a given
 * date (and optional time window) — i.e. who hasn't tapped in yet/at all.
 */
class MissingEntries extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';

    protected static ?string $navigationLabel = 'Missing Entries';

    protected static ?string $title = 'Missing Entries';

    // Reached via the "Missing Entries" button on the Entry Logs page,
    // not as its own sidebar item.
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.missing-entries';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('manage_entry_logs') ?? false;
    }

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'personType' => 'student',
            'date' => now()->toDateString(),
            'fromTime' => null,
            'toTime' => null,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToEntryLogs')
                ->label('Back to Entry Logs')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(AttendanceLogResource::getUrl()),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('personType')
                    ->label('Show')
                    ->options([
                        'student' => 'Students',
                        'employee' => 'Employees',
                    ])
                    ->required()
                    ->live(),
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->default(now())
                    ->live(),
                Forms\Components\TimePicker::make('fromTime')
                    ->label('From time (optional)')
                    ->live(),
                Forms\Components\TimePicker::make('toTime')
                    ->label('To time (optional)')
                    ->live(),
            ])
            ->columns(4)
            ->statePath('data');
    }

    protected function personType(): string
    {
        return $this->data['personType'] ?? 'student';
    }

    protected function isStudent(): bool
    {
        return $this->personType() === 'student';
    }

    protected function personModel(): string
    {
        return $this->isStudent() ? Student::class : Employee::class;
    }

    protected function buildQuery(): Builder
    {
        $model = $this->personModel();
        $date = $this->data['date'] ?? now()->toDateString();
        $from = $this->data['fromTime'] ?? null;
        $to = $this->data['toTime'] ?? null;

        return $model::query()
            ->where('status', 'active')
            ->whereDoesntHave('attendanceLogs', function (Builder $query) use ($date, $from, $to) {
                $query->where('result', 'success')->whereDate('scanned_at', $date);

                if ($from) {
                    $query->whereTime('scanned_at', '>=', $from);
                }

                if ($to) {
                    $query->whereTime('scanned_at', '<=', $to);
                }
            });
    }

    public function table(Table $table): Table
    {
        $isStudent = $this->isStudent();

        return $table
            ->query($this->buildQuery())
            ->heading($isStudent ? 'Students with no entry' : 'Employees with no entry')
            ->columns([
                Tables\Columns\TextColumn::make('id_number')
                    ->label('ID Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->searchable(),
                ...($isStudent ? [
                    Tables\Columns\TextColumn::make('course'),
                    Tables\Columns\TextColumn::make('year_level')
                        ->label('Year Level'),
                ] : []),
                Tables\Columns\TextColumn::make('department')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->options(fn () => $this->personModel()::query()
                        ->distinct()
                        ->pluck('department', 'department')
                        ->sort()
                        ->toArray()),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export to Excel')
                    ->exporter($isStudent ? StudentAbsenceExporter::class : EmployeeAbsenceExporter::class)
                    ->formats([ExportFormat::Xlsx]),
            ]);
    }
}

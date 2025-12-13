<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

class GeocodeExistingStudents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:geocode {--dry-run : Show what would be geocoded without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Geocode existing students who do not have latitude/longitude coordinates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info($dryRun ? 'DRY RUN MODE - No changes will be made' : 'Starting geocoding process...');
        
        $studentsWithoutCoordinates = Student::whereNull('latitude')
            ->orWhereNull('longitude')
            ->get();
            
        if ($studentsWithoutCoordinates->isEmpty()) {
            $this->info('All students already have coordinates!');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$studentsWithoutCoordinates->count()} students without coordinates.");
        
        $geocodingService = new GeocodingService();
        $successCount = 0;
        $failedCount = 0;
        
        $progressBar = $this->output->createProgressBar($studentsWithoutCoordinates->count());
        $progressBar->start();
        
        foreach ($studentsWithoutCoordinates as $student) {
            $this->line("\nProcessing: {$student->first_name} {$student->last_name} from {$student->city}, {$student->province}");
            
            $coordinates = $geocodingService->geocodeAddress(
                $student->address,
                $student->barangay,
                $student->city,
                $student->province,
                $student->postal_code
            );
            
            if ($coordinates) {
                if ($dryRun) {
                    $this->line("  ✓ Would geocode to: {$coordinates['latitude']}, {$coordinates['longitude']}");
                    $this->line("  ✓ Address: {$coordinates['display_name']}");
                } else {
                    $student->update([
                        'latitude' => $coordinates['latitude'],
                        'longitude' => $coordinates['longitude'],
                        'geocoded_address' => $coordinates['display_name']
                    ]);
                    $this->line("  ✓ Geocoded successfully!");
                }
                $successCount++;
            } else {
                $this->line("  ✗ Failed to geocode address");
                $failedCount++;
            }
            
            $progressBar->advance();
            
            // Be respectful to the Nominatim service - add a small delay
            if (!$dryRun) {
                sleep(1);
            }
        }
        
        $progressBar->finish();
        
        $this->newLine(2);
        $this->info("Geocoding completed!");
        $this->info("Successful: {$successCount}");
        $this->info("Failed: {$failedCount}");
        
        if ($dryRun) {
            $this->warn('Run without --dry-run to actually update the database.');
        }
        
        return Command::SUCCESS;
    }
}

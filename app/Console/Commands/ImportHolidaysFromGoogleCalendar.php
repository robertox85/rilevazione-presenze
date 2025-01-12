<?php

namespace App\Console\Commands;

use App\Models\Holiday;
use App\Models\Location;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use om\IcalParser;

class ImportHolidaysFromGoogleCalendar extends Command
{
    protected $signature = 'holidays:import {countryCode} {url}';
    protected $description = 'Import public holidays from Google Calendar for specific country';

    public function handle()
    {
        $countryCode = $this->argument('countryCode');
        $url = $this->argument('url');

        $this->info("Fetching holidays for {$countryCode} from Google Calendar...");

        try {
            // Trova tutte le location per il country code specificato
            $locations = Location::where('country_code', $countryCode)
                ->where('active', true)
                ->get();

            if ($locations->isEmpty()) {
                $this->error("No active locations found for country code {$countryCode}");
                return 1;
            }

            // Scarica e parse del calendario
            $client = new Client();
            $response = $client->get($url, [
                'verify' => false // Attenzione: impostare a true in produzione
            ]);

            $icalContent = $response->getBody()->getContents();
            $ical = new IcalParser();
            $results = $ical->parseString($icalContent);

            $insertedCount = 0;
            $year = date('Y'); // Anno corrente, o potresti passarlo come parametro

            // Process degli eventi
            foreach ($ical->getEvents()->sorted() as $event) {
                $startDate = $event['DTSTART'];
                $description = $event['SUMMARY'];

                // Verifica anno
                if ($startDate->format('Y') === $year) {
                    Log::info('Processing holiday', [
                        'date' => $startDate->format('Y-m-d'),
                        'description' => $description,
                        'country' => $countryCode
                    ]);

                    // Inserimento per ogni location del paese
                    foreach ($locations as $location) {
                        Holiday::updateOrCreate(
                            [
                                'location_id' => $location->id,
                                'holiday_date' => $startDate->format('Y-m-d'),
                            ],
                            [
                                'description' => $description,
                            ]
                        );
                        $insertedCount++;
                    }
                }
            }

            $this->info("Successfully imported {$insertedCount} holidays for {$countryCode}!");
            return 0;

        } catch (\Exception $e) {
            Log::error('Holiday import failed', [
                'country_code' => $countryCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }
    }
}
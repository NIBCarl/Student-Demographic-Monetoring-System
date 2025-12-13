<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const NOMINATIM_BASE_URL = 'https://nominatim.openstreetmap.org';
    private const USER_AGENT = 'StudentDemographicsMonitoringSystem/1.0';

    /**
     * Geocode a full address to get latitude and longitude
     */
    public function geocodeAddress(string $address, string $barangay, string $city, string $province, string $postalCode): ?array
    {
        try {
            // Construct the full address for geocoding
            $fullAddress = $this->constructFullAddress($address, $barangay, $city, $province, $postalCode);
            
            // First try with the full address
            $coordinates = $this->geocodeWithNominatim($fullAddress);
            
            // If that fails, try with just city and province (for Philippines)
            if (!$coordinates) {
                $simplifiedAddress = "{$city}, {$province}, Philippines";
                $coordinates = $this->geocodeWithNominatim($simplifiedAddress);
            }
            
            // If still no result, try with just the city
            if (!$coordinates) {
                $cityAddress = "{$city}, Philippines";
                $coordinates = $this->geocodeWithNominatim($cityAddress);
            }
            
            return $coordinates;
            
        } catch (\Exception $e) {
            Log::error('Geocoding failed: ' . $e->getMessage(), [
                'address' => $address,
                'barangay' => $barangay,
                'city' => $city,
                'province' => $province,
                'postal_code' => $postalCode
            ]);
            
            return null;
        }
    }

    /**
     * Geocode using Nominatim API
     */
    private function geocodeWithNominatim(string $address): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept-Language' => 'en'
            ])->get(self::NOMINATIM_BASE_URL . '/search', [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'ph', // Limit to Philippines
                'addressdetails' => 1
            ]);

            if ($response->successful() && !empty($response->json())) {
                $result = $response->json()[0];
                
                return [
                    'latitude' => (float) $result['lat'],
                    'longitude' => (float) $result['lon'],
                    'display_name' => $result['display_name'] ?? $address
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Nominatim geocoding error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Construct a full address string for geocoding
     */
    private function constructFullAddress(string $address, string $barangay, string $city, string $province, string $postalCode): string
    {
        $parts = [];
        
        if (!empty($address)) {
            $parts[] = $address;
        }
        
        if (!empty($barangay)) {
            $parts[] = "Barangay {$barangay}";
        }
        
        if (!empty($city)) {
            $parts[] = $city;
        }
        
        if (!empty($province)) {
            $parts[] = $province;
        }
        
        if (!empty($postalCode)) {
            $parts[] = $postalCode;
        }
        
        $parts[] = 'Philippines';
        
        return implode(', ', array_filter($parts));
    }

    /**
     * Get coordinates for a city (fallback method)
     */
    public function getCityCoordinates(string $city, string $province = ''): ?array
    {
        $address = $province ? "{$city}, {$province}, Philippines" : "{$city}, Philippines";
        return $this->geocodeWithNominatim($address);
    }
}

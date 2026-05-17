<?php

class DogPark_Scoring {
    
    public static function calculate_best_hour($park_id, $forecast_data) {
        $park = DogPark_Parks::get_park_by_id($park_id);
        $weights = get_option('dogpark_scoring_weights', ['rain' => 40, 'heat' => 25, 'uv' => 15, 'wind' => 10]);
        $hourly_scores = [];
        
        foreach ($forecast_data['hourly'] as $hour_data) {
            $score = 100; // Start with perfect score
            
            // Apply rain penalty (40% weight)
            $rain_penalty = ($hour_data['rain'] / 100) * $weights['rain'];
            $score -= $rain_penalty;
            
            // Apply heat penalty (25% weight)
            $heat_penalty = self::calculate_heat_penalty($hour_data['temp']);
            $score -= $heat_penalty * $weights['heat'] / 10;
            
            // Apply UV penalty (15% weight)
            $uv_penalty = self::calculate_uv_penalty($hour_data['uv']);
            $score -= $uv_penalty * $weights['uv'] / 10;
            
            // Apply wind penalty (10% weight)
            $wind_penalty = self::calculate_wind_penalty($hour_data['wind']);
            $score -= $wind_penalty * $weights['wind'] / 10;
            
            // Apply park modifiers
            if ($park) {
                $modifiers = self::calculate_park_modifiers($park, $hour_data);
                $score += $modifiers;
            }
            
            $hour = $hour_data['hour'];
            $hourly_scores[$hour] = [
                'score' => max(0, min(100, $score)),
                'temp' => $hour_data['temp'],
                'rain' => $hour_data['rain'],
                'uv' => $hour_data['uv'],
                'wind' => $hour_data['wind'],
                'explanation' => self::generate_explanation($hour_data, $park, $weights)
            ];
        }
        
        // Find best hour
        $best_hour = null;
        $best_score = -1;
        foreach ($hourly_scores as $hour => $data) {
            if ($data['score'] > $best_score) {
                $best_score = $data['score'];
                $best_hour = $hour;
            }
        }
        
        // Calculate daily range
        $temps = array_column($forecast_data['hourly'], 'temp');
        $temp_range = [min($temps), max($temps)];
        
        return [
            'best_hour' => $best_hour,
            'best_score' => $best_score,
            'hourly_scores' => $hourly_scores,
            'temp_range' => $temp_range
        ];
    }
    
    private static function calculate_heat_penalty($temp) {
        if ($temp > 35) return 10; // X rating
        if ($temp > 30) return 6;
        if ($temp > 25) return 3;
        if ($temp > 20) return 1;
        return 0;
    }
    
    private static function calculate_uv_penalty($uv) {
        if ($uv > 8) return 10; // X rating
        if ($uv > 6) return 4;
        if ($uv > 3) return 2;
        return 0;
    }
    
    private static function calculate_wind_penalty($wind) {
        if ($wind > 30) return 10; // X rating
        if ($wind > 20) return 5;
        if ($wind > 10) return 2;
        return 0;
    }
    
    private static function calculate_park_modifiers($park, $hour_data) {
        $modifiers = 0;
        $temp = $hour_data['temp'];
        $uv = $hour_data['uv'];
        
        // Shade modifier (only if temp > 25°C or UV > 5)
        if ($temp > 25 || $uv > 5) {
            switch ($park->shade) {
                case 'good': $modifiers += 10; break;
                case 'partial': $modifiers += 5; break;
                case 'bad': $modifiers -= 5; break;
            }
        }
        
        // Water modifier (only if temp > 25°C)
        if ($temp > 25 && $park->water === false) {
            $modifiers -= 15;
        }
        
        return $modifiers;
    }
    
    private static function generate_explanation($hour_data, $park, $weights) {
        $explanation = [];
        
        if ($hour_data['rain'] > 50) {
            $explanation[] = "Υψηλή πιθανότητα βροχής ({$hour_data['rain']}%)";
        }
        
        if ($hour_data['temp'] > 28) {
            $explanation[] = "Υψηλή θερμοκρασία ({$hour_data['temp']}°C)";
        } elseif ($hour_data['temp'] > 25) {
            $explanation[] = "Ζέστη ({$hour_data['temp']}°C)";
        }
        
        if ($hour_data['uv'] > 6) {
            $explanation[] = "Υψηλή υπεριώδης ({$hour_data['uv']})";
        }
        
        if ($hour_data['wind'] > 20) {
            $explanation[] = "Δυνατός άνεμος ({$hour_data['wind']} km/h)";
        }
        
        if ($park) {
            if ($park->shade === 'good' && ($hour_data['temp'] > 25 || $hour_data['uv'] > 5)) {
                $explanation[] = "Καλή σκιά";
            }
            
            if ($park->water === false && $hour_data['temp'] > 25) {
                $explanation[] = "Χωρίς νερό";
            }
        }
        
        return implode(', ', $explanation) ?: 'Ιδανικές συνθήκες';
    }
}
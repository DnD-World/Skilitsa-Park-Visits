<?php

class DogPark_Scheduler {
    
    public static function init() {
        add_action('dogpark_refresh_forecast', [__CLASS__, 'refresh_all_parks']);
    }
    
    public static function schedule_events() {
        if (!wp_next_scheduled('dogpark_refresh_forecast')) {
            // Schedule daily at 3 AM EEST (adjust for DST)
            wp_schedule_event(
                strtotime('today 03:00 EEST'),
                'daily',
                'dogpark_refresh_forecast'
            );
        }
    }
    
    public static function clear_events() {
        wp_clear_scheduled_hook('dogpark_refresh_forecast');
    }
    
    public static function refresh_all_parks() {
        $parks = DogPark_Parks::get_all_parks();
        $today = current_time('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        foreach ($parks as $park) {
            // Refresh today's forecast
            $forecast_today = DogPark_Providers::fetch_forecast($park->latitude, $park->longitude);
            if ($forecast_today) {
                $scoring_today = DogPark_Scoring::calculate_best_hour($park->id, $forecast_today);
                DogPark_Cache::set_cache([
                    'park_id' => $park->id,
                    'date' => $today,
                    'best_hour' => $scoring_today['best_hour'],
                    'temperature_low' => $scoring_today['temp_range'][0],
                    'temperature_high' => $scoring_today['temp_range'][1],
                    'hourly_data' => wp_json_encode($scoring_today['hourly_scores']),
                    'provider' => $forecast_today['provider'],
                    'last_updated' => current_time('mysql')
                ]);
            }
            
            // Refresh tomorrow's forecast (for fallback)
            DogPark_Cache::delete_cache($park->id, $tomorrow); // Ensure fresh data
            $forecast_tomorrow = DogPark_Providers::fetch_forecast($park->latitude, $park->longitude);
            if ($forecast_tomorrow) {
                $scoring_tomorrow = DogPark_Scoring::calculate_best_hour($park->id, $forecast_tomorrow);
                DogPark_Cache::set_cache([
                    'park_id' => $park->id,
                    'date' => $tomorrow,
                    'best_hour' => $scoring_tomorrow['best_hour'],
                    'temperature_low' => $scoring_tomorrow['temp_range'][0],
                    'temperature_high' => $scoring_tomorrow['temp_range'][1],
                    'hourly_data' => wp_json_encode($scoring_tomorrow['hourly_scores']),
                    'provider' => $forecast_tomorrow['provider'],
                    'last_updated' => current_time('mysql')
                ]);
            }
        }
    }
    
    // CLI Command: wp dogpark refresh
    public static function register_cli_command() {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('dogpark refresh', [__CLASS__, 'cli_refresh']);
        }
    }
    
    public static function cli_refresh() {
        self::refresh_all_parks();
        WP_CLI::success('Refreshed all park forecasts.');
    }
}

DogPark_Scheduler::init();
DogPark_Scheduler::register_cli_command();
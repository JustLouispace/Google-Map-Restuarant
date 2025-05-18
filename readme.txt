===========================================
     🍽️ Restaurant Finder - README
===========================================

PROJECT OVERVIEW
-----------------
Restaurant Finder is a web application developed using Laravel 10 and Vue.js. It leverages the Google Maps JavaScript API and Places API to allow users to:

- View nearby restaurants on an interactive map.
- Search restaurants based on current location or custom input.

This project does not use a database at the moment but is structured for easy expansion.

SYSTEM REQUIREMENTS
--------------------
- PHP 8.1 or higher
- Composer
- Node.js and npm
- Laravel 10.x
- Google Maps API Key

INSTALLATION GUIDE
-------------------
1. Clone the project:
   git clone https://github.com/your-username/restaurant-finder.git
   cd restaurant-finder

2. Install PHP and JS dependencies:
   composer install
   npm install

3. Create the environment file:
   cp .env.example .env

4. Generate application key:
   php artisan key:generate

5. Edit the `.env` file and add your Google Maps API Key:
   (Use the same key for both entries)

   DB_CONNECTION=sqlite
   VITE_GOOGLE_MAPS_API_KEY=your-google-maps-api-key
   GOOGLE_MAPS_API_KEY=your-google-maps-api-key

6. Run the application:
   php artisan serve
   (Access at http://localhost:8000)

TROUBLESHOOTING
----------------
1. Map doesn't display?
   - Ensure your GOOGLE_MAPS_API_KEY is correctly set in `.env`
   - Make sure Google Maps JavaScript API and Places API are enabled

2. Search not working?
   - Confirm your internet connection
   - Verify Google Places API is enabled for your API key

3. JavaScript issues?
   - Clear browser cache
   - Run:
     php artisan cache:clear
     php artisan view:clear

DEVELOPER'S NOTE
-----------------
This is my first PHP project. While the core functionality works, there are still a few known issues:

- Restaurant markers may duplicate on repeated searches
- Data loading can be slow in some regions
- Mobile responsiveness needs improvement

Given time constraints, I focused on functionality first and welcome any feedback for future improvements.

PROJECT STRUCTURE
------------------
- app/Http/Controllers     → Laravel Controllers
- app/Services             → Custom logic/services
- resources/js/components  → Vue.js Components
- resources/views          → Blade Templates
- routes                   → Web and API routes
- public                   → Static files (JS, CSS, images)


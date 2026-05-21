# MK3 POS API (Point of Sale)

This is a robust, Laravel-based RESTful API for a modern Point of Sale (POS) system. It provides core POS capabilities, including inventory management, secure transaction processing, comprehensive sales reporting, and advanced AI-driven features for business forecasting.

## Key Features

- **Authentication & Authorization**: Secure API endpoints utilizing Laravel Sanctum. Includes role-based access control and Subscription management (Free vs. PRO tiers).
- **Inventory & Stock Management**: Keep track of product stocks, manage store items, and monitor low inventory.
- **Transactions & Sales**: Process and record sales transactions, maintaining a detailed sales history.
- **Comprehensive Reporting**: Access rich sales reports, revenue trends, average basket sizes, and best-selling products across various timeframes (daily, weekly, monthly, yearly).
- **Payment Gateway Integration**: Built-in support for Midtrans to process payments efficiently.
- **AI-Powered Analytics (PRO Feature)**:
  - **Smart Restock Prediction**: AI evaluates historical data to recommend optimal restock quantities, risk levels, and estimated stock-out dates.
  - **Busy Hours Forecasting**: AI models predict store peak hours and daily revenue, allowing for optimized staff scheduling and inventory preparation.

## Technology Stack

- **Framework**: Laravel (PHP 8.3)
- **Authentication**: Laravel Sanctum
- **Payment Gateway**: Midtrans PHP
- **Database**: Eloquent ORM (MySQL / PostgreSQL / SQLite)
- **AI Integration**: Communicates with an external AI server for predictive modeling.


## Getting Started

Follow these instructions to set up the project locally:

1. **Clone the repository** to your local machine.

2. **Install PHP and Node.js dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Set up environment variables**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database Configuration**:
   Update your `.env` file with your database credentials, then run the migrations:
   ```bash
   php artisan migrate
   ```

5. **Run the Development Server**:
   You can start the local development environment using the following command (which spins up Vite, the Laravel server, and queue worker):
   ```bash
   composer run dev
   ```
   Alternatively, start them separately:
   ```bash
   php artisan serve
   ```

## License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT).

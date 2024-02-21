### Bitcoin prices simple parser
This is a simple application, it uses Binance api to parse Bitcoin/Usdt price in real time just for learning goals. 
It uses user's Binance account to make simple operation automatically:
- sell/buy  BTC if there is change in price.
- send notifications via Telegram bot about selling/buying order
- get the latest price using Telegram webhook.

It has a simple frontend on https://github.com/denis-lmp/bot-frontend for showing price changes.


## To run the local dev environment:
- Navigate to `project` folder
- Copy `.env.example` to `.env`
- Fill database connection details
- Fill you Binance details in the `.env`
- Run `sail up -d`
- Run migration and seeding process.
- Visit Laravel main page for register/login processes: `http://localhost`

- Main files are:
    - `app/Jobs/CryptoBot.php`
    - `app/Services/BinanceService.php`
    - `app/Services/CryptoTradingService.php`
    - `app/Repositories/Contracts/AbstractEloquentRepository.php`
    - `app/Repositories/CryptoTradingBotRepository.php`
    - `app/Repositories/CryptoTradingRepository.php`

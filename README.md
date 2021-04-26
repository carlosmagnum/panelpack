## Panelpack

Admin panel for Laravel

## Installation

Install the package through [Composer](http://getcomposer.org/). 

    composer require decoweb/panelpack:"dev-dev-seven"

Work in progress for Laravel 7

Run vendor:publish
```
php artisan vendor:publish
```
Before installing the migrations, don't forget:
1) to delete the shopping cart migration already published, since Panelpack has its own migration for it;
2) to modify _App\Providers\AppServiceProvider.php_ :
```
use Illuminate\Support\Facades\Schema;
public function boot()
    {
        Schema::defaultStringLength(191);
    }
```
Run the _php artisan migrate_.

To the _App\Http\Kernel.php_, add the following middlewares:
```
'customer' => \App\Http\Middleware\RedirectIfCustomer::class,
'loggedcustomer' => \App\Http\Middleware\RedirectIfNotCustomer::class,
'verifiedcustomer' => \App\Http\Middleware\NotVerifiedCustomer::class,
```
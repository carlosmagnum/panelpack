## Panelpack

Admin panel for Laravel

## Installation

Install the package through [Composer](http://getcomposer.org/). 

    composer require decoweb/panelpack:"dev-version/5-6"

It works well for laravel 5.8.*

Add the following providers to **config/app.php** :
```
Collective\Html\HtmlServiceProvider::class,
Unisharp\Laravelfilemanager\LaravelFilemanagerServiceProvider::class,
Intervention\Image\ImageServiceProvider::class,
Gloudemans\Shoppingcart\ShoppingcartServiceProvider::class,
Watson\Sitemap\SitemapServiceProvider::class,
Decoweb\Panelpack\PanelPackProvider::class,
Unisharp\Ckeditor\ServiceProvider::class,
Decoweb\Panelpack\Providers\ShopServiceProvider::class,
Decoweb\Panelpack\Providers\PicturesServiceProvider::class,
Decoweb\Panelpack\Providers\ComposerServiceProvider::class,
Laravel\Socialite\SocialiteServiceProvider::class,
Barryvdh\DomPDF\ServiceProvider::class,
```
And to aliases:
```
'Form' => Collective\Html\FormFacade::class,
'Html' => Collective\Html\HtmlFacade::class,
'Image' => Intervention\Image\Facades\Image::class,
'Cart' => Gloudemans\Shoppingcart\Facades\Cart::class,
'Socialite' => Laravel\Socialite\Facades\Socialite::class,
'PDF' => Barryvdh\DomPDF\Facade::class,
'Sitemap' => Watson\Sitemap\Facades\Sitemap::class,
```

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
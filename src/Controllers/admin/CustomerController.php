<?php

namespace Decoweb\Panelpack\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Decoweb\Panelpack\Models\Customer;
use DB;
use Illuminate\Support\Facades\Hash;
use App\Notifications\YourAccountWasCreatedManually;
use App\Notifications\AccountCreatedManuallyNeedsConfirmation;
class CustomerController extends Controller
{
    private $customers;
    # Validation regex
    private $alphaDashSpaces = '/^[A-Za-z \-ĂÎÂŞŢăîâşţ]+$/';
    private $alphaDashSpacesNum = '/^[A-Za-z0-9\s\-ĂÎÂŞŢăîâşţ]+$/';
    private $numbers = '/^[0-9]+$/';
    private $address = "/^[A-Za-zĂÎÂŞŢăîâşţ0-9\.\-\s\,]+$/";
    private $alphaNumSlash = '/^[A-Za-z0-9\/\-\.]+$/';


    public function __construct(Customer $customer)
    {
        $this->customers = $customer;
        $this->middleware('web');
        $this->middleware('auth');
    }

    public function index()
    {
        $perPage = DB::table('sys_shop_setups')->where('action','customers_per_page')->pluck('value')->first();
        $ord = ['asc','desc'];
        if (request()->has('name') && in_array( request('name'),$ord ) ) {
            $name = request('name');
            $customers = $this->customers->orderBy('email',$name)->paginate($perPage)->appends('name', request('name'));
        }elseif (request()->has('active') && in_array( request('active'),$ord ) ){
            $customers = $this->customers->orderBy('verified',request('active'))->paginate($perPage)->appends('active', request('active'));
        }else{
            $customers = $this->customers->orderBy('created_at', $ord[1])->paginate($perPage);
        }
        return view('decoweb::admin.shop.customers.index',[
            'customers'     =>$customers,
            'perPage'       => $perPage,
        ]);
    }

    public function updateLimit(Request $request)
    {
        $this->validate($request,[
           'perPage'    => 'required|integer'
        ]);
        DB::table('sys_shop_setups')->where('action','customers_per_page')->update(['value'=>$request->perPage]);
        return redirect('admin/shop/customers');
    }

    public function create()
    {
        return view('decoweb::admin.shop.customers.create');
    }

    private function rules()
    {
        $rules = [
            'account_type'  => 'required|in:0,1',
            'email'         => 'required|email',
            'password'      => 'required|min:6',
            'name'          => "regex:".$this->alphaDashSpaces,
            'phone'         => 'regex:'.$this->numbers,
            'cnp'           => 'digits:13',
            'region'        => "regex:".$this->alphaDashSpaces,
            'city'          => "regex:".$this->alphaDashSpaces,
            'address'       => 'regex:'.$this->address,
            'company'       => "regex:".$this->alphaDashSpacesNum,
            'rc'            => 'regex:'.$this->alphaNumSlash,
            'cif'           => 'alpha_num',
            'bank_account'  => 'alpha_num',
            'bank_name'     => "regex:".$this->alphaDashSpaces,
        ];
        return $rules;
    }

    public function store(Request $request)
    {
        $rules = $this->rules();

        if( $request->has('verified')){
            $rules['verified']  = 'in:0,1';
        }

        $this->validate($request,$rules);

        $customer = new $this->customers();
        $customer->account_type = (int)$request->account_type;
        $customer->email = $request->email;
        $customer->password = Hash::make($request->password);
        $customer->name = $request->name;
        $customer->phone = $request->phone;
        $customer->cnp = $request->cnp;
        $customer->region = $request->region;
        $customer->city = $request->city;
        $customer->company = $request->company;
        $customer->rc = $request->rc;
        $customer->cif = $request->cif;
        $customer->bank_account = $request->bank_account;
        $customer->bank_name = $request->bank_name;
        if( $request->has('verified') && $request->verified == 1 ){
            $customer->verified = 1;
        }

        if( $request->has('notify') && $request->notify == 1 ){
            if( $request->has('verified') && $request->verified == 1 ) {
                $customer->notify(new YourAccountWasCreatedManually($customer, $request->password));
            }else{
                $email_token = md5(Carbon::now());
                $customer->email_token = $email_token;
                $customer->notify(new AccountCreatedManuallyNeedsConfirmation($customer, $request->password, $email_token));
            }
        }
        $customer->address = $request->address;
        $customer->save();

        return redirect('admin/shop/customers')->with('mesaj','Utilizatorul a fost adaugat cu succes!');
    }

    public function edit(Request $request, $id)
    {
        $customer = $this->customers->findOrFail($id);

        return view('decoweb::admin.shop.customers.edit',['customer'=>$customer]);
    }

    public function update(Request $request, $id)
    {
        $customer = $this->customers->find((int)$id);
       // $customer = $this->customers->find((int)500);

        if( ! $customer ){
            return response('Utilizatorul nu exista in baza de date',404)
                ->header('Content-Type', 'text/plain');
        }


        $validationRules = $this->rules();
        unset($validationRules['password']);
        if( $request->has('verified')){
            $validationRules['verified']  = 'in:0,1';
        }
        $this->validate($request, $validationRules);

        $customer->account_type = (int)$request->account_type;
        $customer->email = $request->email;
        $customer->name = $request->name;
        $customer->phone = $request->phone;
        $customer->cnp = $request->cnp;
        $customer->region = $request->region;
        $customer->city = $request->city;
        $customer->company = $request->company;
        $customer->rc = $request->rc;
        $customer->cif = $request->cif;
        $customer->bank_account = $request->bank_account;
        $customer->bank_name = $request->bank_name;
        $customer->address = $request->address;
        if( $request->has('verified') && $request->verified == 1 ){
            $customer->verified = 1;
        }else{
            $customer->verified = 0;
        }
        $customer->save();

        return redirect('admin/shop/customers/'.$customer->id.'/edit')->with('mesaj','Profil actualizat cu success!');
    }

    public function destroy(Request $request, $id)
    {
        $customer = $this->customers->findOrFail((int)$id);
        $customer->delete();
        return redirect('admin/shop/customers')->with('mesaj','Utilizatorul a fost sters din baza de date.');
    }

    public function deleteMultiple(Request $request)
    {
        if( !$request->exists('deleteMultiple') || !$request->has('item') || !is_array($request->item) ){
            return redirect('admin/shop/customers');
        }

        $customersIds = '';
        foreach($request->item as $customerKey=>$on){
            $customersIds[] = (int)$customerKey;
        }

        Customer::whereIn('id',$customersIds)->delete();

        return redirect('admin/shop/customers')->with('mesaj','Utilizatorii au fost stersi cu succes !');
    }
}

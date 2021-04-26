@extends('vendor.decoweb.layouts.app')
@section('header-assets')
    <link rel="stylesheet" href="https://unpkg.com/purecss@2.0.5/build/pure-min.css" >
@endsection
@section('content')
    @php
    $divClass = 'pure-control-group';
    $errorMsg = 'pure-form-message-inline';
    @endphp
    @if(session()->has('mesaj'))
        <p>{{ session()->get('mesaj') }}</p>
    @endif

<h3>Profil utilizator</h3> @if($customer->email) {{ $customer->email }} @endif
    <p>{{ $status }}</p>

<form action="{{ route('customer.update', [$customer->id]) }}" method="post" class="pure-form pure-form-aligned" id="profil">
@csrf @method('PUT')
<fieldset>

<div class="{{ $divClass }}">
    <label for="account_type">Tip cont :</label>
    <select name="account_type" id="account_type">
        <option value="0" @if($customer->account_type == 0) selected @endif>Persoana fizica</option>
        <option value="1" @if($customer->account_type == 1) selected @endif>Persoana juridica</option>
    </select>
    @if ($errors->has('account_type'))
    <span class="{{ $errorMsg }}">{{ $errors->first('account_type') }}</span>
    @endif
</div>
    @if($customer->email === null || empty($customer->email))
    <div class="{{ $divClass }}">
        <label for="email">Email :</label>
         <input type="email" name="email" id="email">
        @if ($errors->has('email'))
        <span class="help-block">{{ $errors->first('email') }}</span>
        @endif
    </div>
    @endif
<div id="pers_fizica" @if(old('account_type') == 1) style="display:none;" @endif>
    <div class="{{ $divClass }}">
        <label for="name">Nume :</label>
        <input type="text" name="name" value="{{ $customer->name }}" id="name">
        @if ($errors->has('name'))
        <span class="{{ $errorMsg }}">{{ $errors->first('name') }}</span>
        @endif
    </div>
    <div class="{{ $divClass }}">
        <label for="phone">Telefon :</label>
        <input type="text" name="phone" id="phone" value="{{ $customer->phone }}">
        @if ($errors->has('phone'))
        <span class="{{ $errorMsg }}">{{ $errors->first('phone') }}</span>
        @endif
    </div>
    <div class="{{ $divClass }}">
        <label for="cnp">CNP :</label>
        <input type="text" name="cnp" id="cnp" value="{{ $customer->cnp }}">
        @if ($errors->has('cnp'))
        <span class="{{ $errorMsg }}">{{ $errors->first('cnp') }}</span>
        @endif
    </div>
    <div class="{{ $divClass }}">
        <label for="region">Judet :</label>
        <input type="text" name="region" id="region" value="{{ $customer->region }}">
        @if ($errors->has('region'))
        <span class="{{ $errorMsg }}">{{ $errors->first('region') }}</span>
        @endif
    </div>
    <div class="{{ $divClass }}">
        <label for="city">Oras :</label>
        <input type="text" name="city" id="city" value="{{ $customer->city }}">
        @if ($errors->has('city'))
        <span class="{{ $errorMsg }}">{{ $errors->first('city') }}</span>
        @endif
    </div>
</div>
<div id="pers_juridica" @if(old('account_type') == 0) style="display:none;" @endif>
    <div class="{{ $divClass }}">
        <label for="company">Companie :</label>
         <input type="text" name="company" id="company" value="{{ $customer->company }}">
        @if ($errors->has('company'))
        <span class="{{ $errorMsg }}">{{ $errors->first('company') }}</span>
        @endif
    </div>
    <div class="{{ $divClass }}">
        <label for="rc">Nr. Reg. Com. :</label>
        <input type="text" name="rc" id="rc" value="{{ $customer->rc }}">
        @if ($errors->has('rc'))
        <span class="{{ $errorMsg }}">{{ $errors->first('rc') }}</span>
        @endif
    </div>
    <div class="{{ $divClass }}">
        <label for="cif">CIF :</label>
        <input type="text" name="cif" id="cif" value="{{ $customer->cif }}">
        @if ($errors->has('cif'))
        <span class="{{ $errorMsg }}">{{ $errors->first('cif') }}</span>
        @endif
    </div>
    <div class="{{ $divClass }}">
        <label for="bank_account">Cont bancar :</label>
        <input type="text" name="bank_account" id="bank_account" value="{{ $customer->bank_account }}">
        @if ($errors->has('bank_account'))
        <span class="{{ $errorMsg }}">{{ $errors->first('bank_account') }}</span>
        @endif
    </div>
    <div class="{{ $divClass }}">
        <label for="bank_name">Nume banca :</label>
        <input type="text" name="bank_name" id="bank_account" value="{{ $customer->bank_name }}">
        @if ($errors->has('bank_name'))
        <span class="{{ $errorMsg }}">{{ $errors->first('bank_name') }}</span>
        @endif
    </div>
</div>
<div class="{{ $divClass }}">
    <label for="address">Adresa :</label>
    <textarea name="address" id="address">{{ $customer->address }}</textarea>
    @if ($errors->has('address'))
    <span class="{{ $errorMsg }}">{{ $errors->first('address') }}</span>
    @endif
</div>
<div class="pure-controls">
    <input type="submit" class="pure-button pure-button-primary" value="Modifica">
</div>
</fieldset>
    </form>
    <a href="{{ url('customer/newPassword') }}" class="pure-button">Schimba parola</a>
    <a href="{{ url('customer/myOrders') }}" class="pure-button">Comenzile mele</a>

@endsection
@section('footer-assets')
<script src="{{ asset('assets/admin/vendors/decoweb/js/account_type.js') }}"></script>
@endsection

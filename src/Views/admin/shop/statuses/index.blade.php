@extends('vendor.decoweb.admin.layouts.master')
@section('section-title') Status comenzi @endsection
@section('section-content')

<div class="table-responsive">
    <table class="table">
        <thead>
        <tr>
            <th>Nume</th>
            <th class="text-center">Vizibil</th>
            <th class="text-center">Actiuni</th>
        </tr>
        </thead>
        <tbody>
        @foreach($statuses as $status)
        <tr>
            <td>{{ $status->name }}</td>
            <td class="text-center">@if($status->visible == 1) <span class="panelIcon visible"></span> @else <span class="panelIcon notVisible"></span> @endif</td>
            <td class="text-center"><a data-toggle="tooltip" data-placement="top" href="{{ url('admin/shop/statuses/'.$status->id.'/edit/') }}" class="panelIcon editItem" title="Editeaza"></a></td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
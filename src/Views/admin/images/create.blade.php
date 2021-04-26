@extends('vendor.decoweb.admin.layouts.master')
@section('section-title')
    <a href="{{ url('admin/core/'.$tabela) }}">{{ $pageName }}</a> / {{ $record->$name }}
@endsection
@section('section-content')
<form action="{{ route('store.pic', [$tabela, $record->id]) }}" method="POST" class="form-horizontal" enctype="multipart/form-data">
        @csrf
        @method('POST')
    <div class="form-group"><label for="description" class="col-sm-2 control-label">Titlu poza: </label>
        <div class="col-sm-5">
            <textarea name="description" id="description" class="form-control textarea-small" placeholder="(maxim 50 de caractere)"></textarea>
        </div>
    </div>
    <div class="form-group"><label for="pic" class="col-sm-2 control-label">Alege o poza:</label>
        <div class="col-sm-5">
            <input type="file" name="pic" class="form-control">
        </div>
    </div>
    <div class="col-sm-10 col-sm-offset-2">
        <input type="submit" class="btn btn-primary btn-sm" value="Adauga poza">
        <a class="btn btn-default btn-sm" href="{{ url('admin/core/'.$tabela) }}">Renunta</a>
        <button class="btn btn-default btn-sm" type="reset" value="Reset">Reset</button>
    </div>
</form>

    <span style="display: block; height: 40px;"></span>
    @if($poze->count() != 0)
    <form action="{{ route('update.picsOrder', [$idTabela, $record->id]) }}" method="post" class="form-horizontal">
        @csrf
        @method('POST')
<div class="table-responsive">
    <table class="table">
        <thead>
        <tr>
            <th>Imagine</th>
            <th>Titlu</th>
            <th class="text-center">Ordine</th>
            <th class="text-center">Actiuni</th>
        </tr>
        </thead>
        <tbody>
    @foreach($poze as $poza)
        <tr>
            <td style="width: 136px;">
                <img src="{{ url('images/small/'.$tabela.'/'.$record->id.'/'.'thumb_'.$poza->name) }}" alt="{{ Str::limit($poza->description, 50) }}" title="{{ Str::limit($poza->description, 50) }}" data-toggle="tooltip" data-placement="right">
            </td>
            <td>
                <textarea name="description_{{ $poza->id }}" cols="50" rows="10" class="form-control textarea-small">{{ $poza->description }}</textarea>
            </td>
            <td class="text-center">
                <input type="text" name="ordine_{{ $poza->id }}" value="{{ $poza->ordine }}" class="numar margin-top-34">
            </td>
            <td class='text-center'>
                <a data-toggle="tooltip" style="margin-top: 35px;" data-placement="top" href="{{ url('admin/core/deletePic/'.$poza->id) }}" class="panelIcon deleteItem" title='Sterge' onclick="return confirm('Sunteti sigur ca doriti sa stergeti?')"></a>
            </td>
        </tr>
    @endforeach
        </tbody>
    </table>
</div>
    <div class="col-sm-12">
        <input type="submit" value="Modifica" class="btn btn-success btn-sm">
    </div>
    </form>
    @else
        {{ $noImages }}
    @endif
    <hr>
    <a href="{{ url('admin/core/'.$tabela) }}" class="btn btn-default">Inapoi la listare</a>
    <a href="{{ url('admin/core/'.$tabela.'/edit/'.$record->id) }}" class="btn btn-default">Inapoi la editare</a>
@endsection

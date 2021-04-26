@extends('vendor.decoweb.admin.layouts.master')
@section('section-title')
<a href="{{ url('admin/core/'.$tabela) }}">{{ $pageName }}</a> / {{ $record->$name }}
@endsection
@section('section-content')
<form action="{{ route('store.file', [$tabela, $record->id]) }}" method="post" enctype="multipart/form-data" class="form-horizontal">
        @csrf
        @method('POST')
    <div class="form-group">
        <label for="title" class="col-sm-2 control-label">Nume fisier:</label>
        <div class="col-sm-5" id="root">
            <input type="text" name="title" class="form-control" id="title" placeholder="(maxim 50 de caractere)" value="{{ old('title') }}">
        </div>
    </div>
    <div class="form-group">
        <label for="file" class="col-sm-2 control-label">Alege un fisier:</label>
        <div class="col-sm-5">
            <input name="file" type="file" class="form-control" id="file">
        </div>
    </div>
    <div class="col-sm-10 col-sm-offset-2">
        <input type="submit" value="Adauga fisier" class="btn btn-primary btn-sm">
        <a class="btn btn-default btn-sm" href="{{ url('admin/core/'.$tabela) }}">Renunta</a>
        <button class="btn btn-default btn-sm" type="reset" value="Reset">Reset</button>
    </div>
</form>
    <span style="display: block; height: 40px;"></span>
    @if($files->count() != 0)
<form action="{{ route('update.filesOrder', [$idTabela, $record->id]) }}" method="post" class="form-horizontal">
    @csrf
    @method('POST')
    <div class="table-responsive">
        <table class="table">
            <thead>
            <tr>
                <th>Fisier</th>
                <th>Nume</th>
                <th class="text-center">Ordine</th>
                <th class="text-center">Actiuni</th>
            </tr>
            </thead>
            <tbody>
            @foreach($files as $file)
                <tr>
                    <td style="width: 136px;">
                        <a target="_blank" href="{{ asset('fisiere/'.$tabela.'/'.$record->id.'/'.$file->name) }}" class="btn btn-info btn-sm">{{ $file->title }}</a>
                    </td>
                    <td>
                        <input type="text" name="title_{{ $file->id }}" value="{{ $file->title }}" class="form-control input-sm">
                    </td>
                    <td class="text-center">
                        <input type="text" name="ordine_{{ $file->id }}" value="{{ $file->ordine }}" class="numar">
                    </td>
                    <td class='text-center'>
                        <a data-toggle="tooltip" style="" data-placement="top" href="{{ url('admin/core/deleteFile/'.$file->id) }}" class="panelIcon deleteItem" title='Sterge' onclick="return confirm('Sunteti sigur ca doriti sa stergeti?')"></a>
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
        {{ $noFiles }}
    @endif
    <hr>
    <a href="{{ url('admin/core/'.$tabela) }}" class="btn btn-default">Inapoi la listare</a>
    <a href="{{ url('admin/core/'.$tabela.'/edit/'.$record->id) }}" class="btn btn-default">Inapoi la editare</a>
@endsection

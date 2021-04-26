@extends('vendor.decoweb.admin.layouts.master')
@section('section-title') Editare tabela <strong>{{ $settings['config']['tableName'] }}</strong> @endsection
@section('footer-assets')
<script>
    $(document).ready(function(){

        $( "select[id^='tip_']" ).change(function() {
            var id = '';
            id += $( this ).attr('id');
            var counter = id.replace(/tip_/,'');

            var sel = 'select_';
            var txt = 'text_';
            var select = sel.concat(counter);
            var text = txt.concat(counter);
            switch( $("#" + id + " option:selected").text() ){
                case 'select':
                    $("#" + select).css('display','block');
                    $("#" + text).css('display','none');
                    break;
                case 'text':
                    $("#" + select).css('display','none');
                    $("#" + text).css('display','block');
                    break;
                default:
                    $("#" + text).css('display','none');
                    $("#" + select).css('display','none');
            }
        });

        var table;
        $('#tableName').change( function(){
            if (typeof table !== 'undefined') {
                $(".selectTable option[value='" + table + "']").remove();
            }

            table = $('#tableName').val();
            $('.selectTable').prepend($('<option>', {
                value: table,
                text: table
            }));

        });

        $("#addField").click(function(event){
            event.preventDefault();
//            $('#myTable tr:last').after("");
        });

        $("#myTable").tableDnD();
    });
</script>
@endsection
@section('section-content')
    @if(session()->has('mesaj'))
        <div class="alert alert-success text-center" role="alert">
            {{ session()->get('mesaj') }}
        </div>
    @endif

{!! Form::open(['method'=>'PUT','route'=>['tables.update',$table->id],'class'=>'form-horizontal']) !!}
<table class="table" id="myTable">
    <tbody>
        <tr class="nodrop nodrag"><th colspan="2" class="active">Setari afisare</th></tr>
    <tr class="nodrop nodrag"><td>Nume tabela</td><td><input type="text" name="tableName" id="tableName" value="{{ $settings['config']['tableName'] }}"></td></tr>
    <tr class="nodrop nodrag"><td>Nume pagina</td><td><input type="text" name="pageName" value="{{ $settings['config']['pageName'] }}"></td></tr>
    <tr class="nodrop nodrag"><td>Model</td><td><input type="text" name="model" value="{{ $settings['config']['model'] }}"></td></tr>
    <tr class="nodrop nodrag"><td>Nume afisat</td><td><input type="text" name="displayedName" value="{{ $settings['config']['displayedName'] }}"></td></tr>
    <tr class="nodrop nodrag"><td>Nume friendly afisat</td><td><input type="text" name="displayedFriendlyName" value="{{ $settings['config']['displayedFriendlyName'] }}"></td></tr>
    <tr class="nodrop nodrag"><td>Limita pe pagina</td><td><input type="number" name="limitPerPage" value="{{ $settings['config']['limitPerPage'] }}"></td></tr>
        <tr class="nodrop nodrag"><th colspan="2" class="active">Functii</th></tr>
    <tr class="nodrop nodrag"><td>Adaugare</td>
        <td><input type="checkbox" name="functionAdd"
                   <?=($settings['config']['functionAdd'] == 1)?'checked="checked"':'';?>
                   value="1">
        </td>
    </tr>
    <tr class="nodrop nodrag"><td>Editare</td>
        <td><input type="checkbox" name="functionEdit"
                   <?=($settings['config']['functionEdit'] == 1)?'checked="checked"':'';?>
                   value="1">
        </td>
    </tr>
    <tr class="nodrop nodrag"><td>Stergere</td>
        <td><input type="checkbox" name="functionDelete"
                   <?=($settings['config']['functionDelete'] == 1)?'checked="checked"':'';?>
                   value="1">
        </td>
    </tr>
    <tr class="nodrop nodrag"><td>Seteaza ordinea</td>
        <td><input type="checkbox" name="functionSetOrder"
                   <?=($settings['config']['functionSetOrder'] == 1)?'checked="checked"':'';?>
                   value="1">
        </td>
    </tr>
    <tr class="nodrop nodrag"><td>Imagini</td>
        <td><div class="col_1">
                <input type="checkbox" name="functionImages"
                       <?=($settings['config']['functionImages'] == 1)?'checked="checked"':'';?>
                value="1">
            </div>
            <div class="col_2">Maxim: <input class="numar" type="number" min="0" name="imagesMax" value="{{$settings['config']['imagesMax']}}"></div>
        </td>
    </tr>
    <tr class="nodrop nodrag"><td>Fisiere</td>
        <td><div class="col_1">
                <input type="checkbox" name="functionFile"
                       <?=($settings['config']['functionFile'] == 1)?'checked="checked"':'';?>
                value="1">
            </div>
            <div class="col_2">Maxim: <input class="numar" type="number" min="0" name="filesMax" value="{{ $settings['config']['filesMax'] }}"></div>
            <div class="col_2">Extensii permise: <input type="text" name="filesExt" value="pdf"></div>
        </td>
    </tr>
    <tr class="nodrop nodrag"><td>Vizibil pe site</td>
        <td><input type="checkbox" name="functionVisible"
               <?=($settings['config']['functionVisible'] == 1)?'checked="checked"':'';?>
               value="1">
        </td>
    </tr>
    <tr class="nodrop nodrag"><td>Creaza tabela</td><td><input type="checkbox" name="functionCreateTable" checked="checked" value="1"></td></tr>
    <tr class="nodrop nodrag"><td>Recursiv</td>
        <td><div class="col_1">
                <input type="checkbox" name="functionRecursive"
                       <?=($settings['config']['functionRecursive'] == 1)?'checked="checked"':'';?>
                       value="1">
            </div>
            <div class="col_2">Nivel maxim: <input type="number" name="recursiveMax" value="{{ $settings['config']['recursiveMax'] }}"></div>
        </td>
    </tr>
        <tr class="nodrop nodrag"><th colspan="2" class="active">Mesaje</th></tr>
    <tr class="nodrop nodrag"><td>Adauga</td><td><input class="form-control input-sm" type="text" name="add" value="{{ $settings['messages']['add'] }}"></td></tr>
    <tr class="nodrop nodrag"><td>Editeaza</td><td><input class="form-control input-sm" type="text" name="edit" value="{{ $settings['messages']['edit'] }}"></td></tr>
    <tr class="nodrop nodrag"><td>Nu sunt imagini</td><td><input class="form-control input-sm" type="text" name="no_images" value="{{ $settings['messages']['no_images'] }}"></td></tr>
    <tr class="nodrop nodrag"><td>Nu sunt fisiere</td><td><input class="form-control input-sm" type="text" name="no_files" value="{{ $settings['messages']['no_files'] }}"></td></tr>
    <tr class="nodrop nodrag"><td>A fost adaugat</td><td><input class="form-control input-sm" type="text" name="added" value="{{ $settings['messages']['added'] }}"></td></tr>
    <tr class="nodrop nodrag"><td>A fost sters</td><td><input class="form-control input-sm" type="text" name="deleted" value="{{ $settings['messages']['deleted'] }}"></td></tr>
        <tr class="nodrop nodrag"><th colspan="2" class="active">Filtre</th></tr>
    <tr class="nodrop nodrag"><td>Numele campurilor din tabel separate cu "," (virgula)</td>
        <?php
              if(!empty($settings['filter']) && is_array($settings['filter'])){
                $filter = implode(',',$settings['filter']);
              }else{
                $filter='';
              }
        ?>
        <td><input class="form-control input-sm" type="text" name="filter" value="{{ $filter }}"></td>
    </tr>

    <tr class="nodrop nodrag"><th colspan="2" class="active nodrop nodrag">Coloane |
            <button id="addField" class="btn btn-default btn-sm">Adauga coloana</button></th></tr>
    <?php $count=0; ?>
    @foreach($settings['elements'] as $name => $field)
    <tr>
        <td style="width:40%;">
            <div class="form-group">
                <label for="friendlyName_{{ $count }}" class="col-md-4 control-label">Nume friendly</label>
                <div class="col-md-8">
                    <input type="text" class="form-control" name="elements[{{ $name }}][friendlyName]" id="friendlyName_{{ $count }}" value="{{ $field['friendlyName'] }}">
                </div>
            </div>
            <div class="form-group">
                <label for="dbName_{{ $count }}" class="col-md-4 control-label">Nume DB</label>
                <div class="col-md-8">
                <input type="text" class="form-control" name="elements[{{ $name }}][databaseName]" id="dbName_{{ $count }}" value="{{ $name }}">
                </div>
            </div>
            <div class="form-group">
                {!! Form::label('tip_'.$count,'Tip:',['class'=>'col-sm-4 control-label']) !!}
                <div class="col-md-8">
                    {!! Form::select("elements[$name][type]",$types,$field['type'],['class'=>'form-control','id'=>'tip_'.$count]) !!}
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-4 control-label">Obligatoriu</label>
                <div class="radio col-md-8">
                    <?php
                        if ($field['required'] == 1){
                            $yes = true;
                            $yesValue = 1;
                            $no = false;
                            $noValue = 0;
                        }else{
                            $yes = false;
                            $yesValue = 0;
                            $no = true;
                            $noValue = 1;
                        }
                    ?>
                    <label class="radio-inline">{!! Form::radio("elements[$name][required]", $noValue, $no) !!} Nu</label>
                    <label class="radio-inline">{!! Form::radio("elements[$name][required]", $yesValue, $yes) !!} Da</label>
                </div>
            </div>
            <div class="form-group">
                <label for="colType_{{ $count }}" class="col-md-4 control-label">Tip coloana</label>
                <div class="col-md-8">
                    <input type="text" class="form-control" name="elements[{{ $name }}][colType]" id="colType_{{ $count }}" value="{{ $field['colType'] }}">
                </div>
            </div>
        </td>
        <td>
            <div class="form-group" style="@if($field['type'] == 'select') display:none @else display:block @endif;">
                <div class="col-md-6" id="text_{{ $count }}" >
                    <label class="checkbox-inline">
                       <input type="checkbox" name="elements[{{ $name }}][readonly]" id="text_{{ $count }}" {{ (isset($field['readonly']) && $field['readonly'] == 1)?'checked="checked"':'' }} value="1"> Readonly
                    </label>
                </div>
            </div>

            <span id="select_{{ $count }}" style="@if($field['type'] == 'select') display:block !important @else display:none @endif;">
                <div class="form-group">
                    <div class="col-md-8 col-md-offset-3 checkbox">
                        <label class="checkbox-inline">
                            <?php $multiple = (isset($field['selectMultiple']) && $field['selectMultiple'] == 1)?'checked="checked"':''; ?>
                            <input type="checkbox" name="elements[{{ $name }}][selectMultiple]" {{ $multiple }} value="1"> Multiplu
                        </label>
                    </div>
                </div>
                <div class="form-group">
                        {!! Form::label('','Prima valoare:',['class'=>'col-md-3 control-label']) !!}
                    <div class="col-md-8">
                        <?php $selectFirstEntry = (isset($field['selectFirstEntry']))?$field['selectFirstEntry']:null; ?>
                        {!! Form::text("elements[$name][selectFirstEntry]", $selectFirstEntry, ['class'=>'form-control', 'id'=>'']) !!}
                    </div>
                </div>
                <div class="form-group">
                    {!! Form::label('','Din tabelul:',['class'=>'col-sm-3 control-label']) !!}
                    <div class="col-sm-8">
                        <?php $selectTable = (isset($field['selectTable']))?$field['selectTable']:null; ?>
                    {!! Form::select("elements[$name][selectTable]",$tabele ,$selectTable,['class'=>'form-control selectTable']) !!}
                    </div>
                </div>
                <div class="form-group">
                        {!! Form::label('','Valori fixe:',['class'=>'col-md-3 control-label']) !!}
                    <div class="col-md-8">
                        <?php $selectExtra = (isset($field['selectExtra']))?$field['selectExtra']:null; ?>
                        {!! Form::textarea("elements[$name][selectExtra]", $selectExtra, ['class'=>'form-control', 'id'=>'','rows'=>2]) !!}
                    </div>
                </div>
            </span>

        </td>
    </tr>
        <?php $count++; ?>
    @endforeach
    </tbody>
</table>
<div class="col-sm-12">
    {!! Form::submit('Submit',['class' => 'btn btn-success btn-sm']) !!}
</div>
{!! Form::close() !!}


@endsection

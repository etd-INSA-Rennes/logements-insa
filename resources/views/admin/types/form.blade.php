{!! Form::model($type, ['class' => 'form-horizontal', 'url' => route("types.$action", $type), 'method' => $action == "store" ? "Post" : "Put"]) !!}

  <div class="form-group">
    <label class="control-label">Nom</label>
    {!! Form::text('name', null, ['class' => 'form-control']) !!}
  </div>

  <div class="form-group">
    <label class="control-label">Slug</label>
    {!! Form::text('slug', null, ['class' => 'form-control']) !!}
  </div>

  <div class="form-group">
    <button type="submit" class="btn btn-primary">
      @if ($action == 'store')
        Envoyer
      @else
        Mettre à jour
      @endif
    </button>
  </div>

{!! Form::close() !!}
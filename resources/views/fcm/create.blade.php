@extends('layouts.app')

@section('content')
<div class="container">
  <div class="row">
    <div class="col-md-8 col-md-offset-2">
      <div class="panel panel-default">
        <div class="panel-heading">
          FCM 메시지 작성기
          <small>
            테스트 편의 및 PoC(Proof of Concept)을 위해 만든 폼입니다.
          </small>
        </div>
        <div class="panel-body">
          <form class="form-horizontal" role="form" method="POST" action="{{ route('fcm.send') }}">
            {{ csrf_field() }}
            <input type="hidden" name="user_id" value="{{ $user_id }}">


            <div class="form-group{{ $errors->has('first_field') ? ' has-error' : '' }}">
              <label for="first_field" class="col-md-4 control-label">첫번째 필드</label>

              <div class="col-md-6">
                <input id="first_field" type="first_field" class="form-control" name="first_field" value="{{ old('first_field') }}">

                @if ($errors->has('first_field'))
                <span class="help-block">
                    <strong>{{ $errors->first('first_field') }}</strong>
                  </span>
                @endif
              </div>
            </div>

            <div class="form-group{{ $errors->has('second_field') ? ' has-error' : '' }}">
              <label for="second_field" class="col-md-4 control-label">두번째 필드</label>

              <div class="col-md-6">
                <input id="second_field" type="second_field" class="form-control" name="second_field" value="{{ old('second_field') }}">

                @if ($errors->has('second_field'))
                <span class="help-block">
                    <strong>{{ $errors->first('second_field') }}</strong>
                  </span>
                @endif
              </div>
            </div>

            <div class="form-group">
              <div class="col-md-8 col-md-offset-4">
                <button type="submit" class="btn btn-primary">
                  전송하기
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

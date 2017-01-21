@extends('layouts.app')

@section('content')
<div class="container">
  <div class="row">
    <div class="col-md-8 col-md-offset-2">
      <div class="panel panel-default">
        <div class="panel-heading">
          사용자 목록
          <small class="text-muted">
            <p>테스트 편의 및 PoC(Proof of Concept)을 위해 만든 페이지입니다. <br>
            사용자에게 연결된 단말기가 있으면 목록과 "FCM 보내기" 버튼이 출력됩니다. </p>
          </small>
        </div>
        <div class="panel-body">
          @if (session('response'))
          <div class="alert alert-info">
            {{ session('response') }}
          </div>
          @endif

          <ul>
            @foreach ($users as $user)
            <li>
              @if ($user->devices->count())
              <a href="{{ route('fcm.create', $user->id) }}" class="pull-right btn-primary btn-sm">
                FCM 보내기
              </a>
              @endif
              <h4>{{ $user->name }}
                <small class="text-muted">
                  {{ $user->email }}
                  •
                  Signed up at {{ $user->created_at->diffForHumans() }}
                </small>
              </h4>
              <ul>
                @forelse ($user->devices as $device)
                  <li>
                    {{ $device->device_id }}

                    @if ($broadcaster = $device->push_service_enum)
                    • {{ title_case($broadcaster) }}
                    @endif

                    @if ($os = $device->os_enum)
                    • {{ title_case($os) }}
                    @endif

                    @if ($model = $device->model)
                    • {{ $model }}
                    @endif

                    @if ($operator = $device->operator)
                    • {{ $operator }}
                    @endif

                    @if ($api = $device->api_level)
                    • API {{ $api }}
                    @endif
                  </li>
                @empty
                @endforelse
              </ul>
            </li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

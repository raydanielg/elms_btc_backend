@extends('installer.layout')

@section('content')
    <div class="steps">
        <div class="step done">
            <div class="step-circle">&#10003;</div>
            <div class="step-label">Welcome</div>
        </div>
        <div class="step active">
            <div class="step-circle">2</div>
            <div class="step-label">Requirements</div>
        </div>
        <div class="step">
            <div class="step-circle">3</div>
            <div class="step-label">Database</div>
        </div>
        <div class="step">
            <div class="step-circle">4</div>
            <div class="step-label">Complete</div>
        </div>
    </div>

    <h2 style="font-size: 1.1rem; color: #1e293b; margin-bottom: 1rem;">Server Requirements</h2>

    <ul class="req-list">
        @foreach ($requirements as $name => $passed)
            <li class="req-item {{ $passed ? 'ok' : 'fail' }}">
                <span class="req-name">{{ $name }}</span>
                <span class="req-status {{ $passed ? 'ok' : 'fail' }}">{{ $passed ? 'Passed' : 'Failed' }}</span>
            </li>
        @endforeach
    </ul>

    <h2 style="font-size: 1.1rem; color: #1e293b; margin: 1.5rem 0 0.75rem;">Folder Permissions</h2>

    <ul class="req-list">
        @foreach ($folders as $name => $writable)
            <li class="req-item {{ $writable ? 'ok' : 'fail' }}">
                <span class="req-name">{{ $name }}</span>
                <span class="req-status {{ $writable ? 'ok' : 'fail' }}">{{ $writable ? 'Writable' : 'Not Writable' }}</span>
            </li>
        @endforeach
    </ul>

    @if (!$allPassed)
        <div class="alert alert-error mt-2">
            Some requirements are not met. Please fix them before continuing.
        </div>
    @endif

    <div class="actions">
        <a href="/installer" class="btn btn-outline">&larr; Back</a>
        <a href="/installer/database" class="btn btn-primary" @if(!$allPassed) style="pointer-events: none; opacity: 0.5;" @endif>Continue &rarr;</a>
    </div>
@endsection

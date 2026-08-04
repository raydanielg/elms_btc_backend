@extends('installer.layout')

@section('content')
    <div class="steps">
        <div class="step done">
            <div class="step-circle">&#10003;</div>
            <div class="step-label">Welcome</div>
        </div>
        <div class="step done">
            <div class="step-circle">&#10003;</div>
            <div class="step-label">Requirements</div>
        </div>
        <div class="step done">
            <div class="step-circle">&#10003;</div>
            <div class="step-label">Database</div>
        </div>
        <div class="step done">
            <div class="step-circle">&#10003;</div>
            <div class="step-label">Complete</div>
        </div>
    </div>

    <div class="text-center">
        <div class="icon-large">&#9989;</div>
        <h2 style="font-size: 1.3rem; color: #16a34a;">Installation Complete!</h2>
        <p class="text-muted mt-2">
            The Butimba ELMS Backend has been successfully installed.
            Database migrations and seeders have been executed.
        </p>
    </div>

    @if (!empty($migrateOutput))
        <div class="mt-4">
            <strong style="font-size: 0.85rem; color: #1e293b;">Migration Output:</strong>
            <div class="output-box">{{ $migrateOutput }}</div>
        </div>
    @endif

    @if (!empty($seedOutput))
        <div class="mt-2">
            <strong style="font-size: 0.85rem; color: #1e293b;">Seeder Output:</strong>
            <div class="output-box">{{ $seedOutput }}</div>
        </div>
    @endif

    <div class="alert alert-success mt-4">
        Your API is now live. You can access the API documentation (Swagger UI) or check the system status.
    </div>

    <div class="actions">
        <a href="/" class="btn btn-outline">System Status</a>
        <a href="/docs" class="btn btn-primary">View API Docs (Swagger) &rarr;</a>
    </div>
@endsection

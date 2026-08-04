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
        <div class="step active">
            <div class="step-circle">3</div>
            <div class="step-label">Database</div>
        </div>
        <div class="step">
            <div class="step-circle">4</div>
            <div class="step-label">Complete</div>
        </div>
    </div>

    <h2 style="font-size: 1.1rem; color: #1e293b; margin-bottom: 1rem;">Database Configuration</h2>

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <form action="/installer/install" method="POST">
        @csrf
        <div class="form-group">
            <label for="APP_NAME">Application Name</label>
            <input type="text" id="APP_NAME" name="APP_NAME" value="{{ $defaults['APP_NAME'] }}" required>
        </div>

        <div class="form-group">
            <label for="APP_URL">Application URL</label>
            <input type="url" id="APP_URL" name="APP_URL" value="{{ $defaults['APP_URL'] }}" required>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="DB_HOST">Database Host</label>
                <input type="text" id="DB_HOST" name="DB_HOST" value="{{ $defaults['DB_HOST'] }}" required>
            </div>
            <div class="form-group">
                <label for="DB_PORT">Database Port</label>
                <input type="text" id="DB_PORT" name="DB_PORT" value="{{ $defaults['DB_PORT'] }}" required>
            </div>
        </div>

        <div class="form-group">
            <label for="DB_DATABASE">Database Name</label>
            <input type="text" id="DB_DATABASE" name="DB_DATABASE" value="{{ $defaults['DB_DATABASE'] }}" required placeholder="e.g. butimba_elms">
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="DB_USERNAME">Database Username</label>
                <input type="text" id="DB_USERNAME" name="DB_USERNAME" value="{{ $defaults['DB_USERNAME'] }}" required>
            </div>
            <div class="form-group">
                <label for="DB_PASSWORD">Database Password</label>
                <input type="password" id="DB_PASSWORD" name="DB_PASSWORD" value="{{ $defaults['DB_PASSWORD'] }}" placeholder="Leave empty if none">
            </div>
        </div>

        <div class="alert alert-info">
            The installer will test the connection, write the .env file, run migrations, and seed the database.
        </div>

        <div class="actions">
            <a href="/installer/requirements" class="btn btn-outline">&larr; Back</a>
            <button type="submit" class="btn btn-primary">Install Now &rarr;</button>
        </div>
    </form>
@endsection

@extends('installer.layout')

@section('content')
    <div class="steps">
        <div class="step active">
            <div class="step-circle">1</div>
            <div class="step-label">Welcome</div>
        </div>
        <div class="step">
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

    <div class="text-center">
        <div class="icon-large">&#128218;</div>
        <h2 style="font-size: 1.2rem; color: #1e293b;">Welcome to the Installer</h2>
        <p class="text-muted mt-2">
            This wizard will guide you through setting up the Butimba ELMS Backend.
            You will need your database credentials ready before proceeding.
        </p>
    </div>

    <div class="alert alert-info mt-4">
        <strong>Before you begin:</strong> Make sure you have created a MySQL database
        and have the database name, username, and password available.
    </div>

    <div class="actions">
        <span></span>
        <a href="/installer/requirements" class="btn btn-primary">Get Started &rarr;</a>
    </div>
@endsection

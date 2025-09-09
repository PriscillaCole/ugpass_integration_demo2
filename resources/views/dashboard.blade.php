<!DOCTYPE html>
<html>
<head>
    <title>UgPass Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow-lg p-4">
        <h2 class="mb-3">Welcome, {{ $user['daes_claims']['name'] ?? 'User' }} 🎉</h2>
        <p><strong>Email:</strong> {{ $user['daes_claims']['email'] ?? 'N/A' }}</p>

        <hr>

        <h4>Next Steps</h4>
        <ul>
            <li><a href="{{ route('sign.ui') }}" class="btn btn-primary mt-2">Upload & Sign Documents</a></li>
            <li><a href="{{ route('ugpass.logout') }}" class="btn btn-danger mt-2">Logout</a></li>
        </ul>
    </div>
</div>
</body>
</html>


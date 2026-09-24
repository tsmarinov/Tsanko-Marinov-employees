<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pair of employees who have worked together</title>
    <style>
        body {
            font-family: -apple-system, system-ui, sans-serif;
            max-width: 720px;
            margin: 40px auto;
            padding: 0 16px;
            color: #1b1b18;
        }
        form {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        button {
            padding: 8px 16px;
            cursor: pointer;
        }
        button:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 24px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px 12px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
        }
        #spinner {
            display: none;
            margin-top: 16px;
            align-items: center;
            gap: 8px;
        }
        #error {
            display: none;
            margin-top: 16px;
            color: #b00020;
        }
        #summary {
            margin-top: 24px;
            font-weight: 600;
        }
        .spin {
            width: 16px;
            height: 16px;
            border: 2px solid #ccc;
            border-top-color: #333;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <h1>Pair of employees who have worked together</h1>
    <p>Pick a CSV file to find the pair of employees who worked together the longest.</p>

    <form id="upload-form">
        <input type="file" name="csv" id="csv" accept=".csv,text/csv" required>
        <button type="submit">Upload &amp; process</button>
    </form>

    <div id="spinner"><span class="spin"></span> Processing…</div>
    <div id="error"></div>
    <div id="summary"></div>

    <table id="results" style="display:none">
        <thead>
            <tr>
                <th>Employee ID #1</th>
                <th>Employee ID #2</th>
                <th>Project ID</th>
                <th>Days worked</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <script>
        const form = document.getElementById('upload-form');
        const spinner = document.getElementById('spinner');
        const errorBox = document.getElementById('error');
        const summary = document.getElementById('summary');
        const table = document.getElementById('results');
        const tbody = table.querySelector('tbody');
        const submitButton = form.querySelector('button');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            errorBox.style.display = 'none';
            summary.textContent = '';
            table.style.display = 'none';
            tbody.innerHTML = '';
            spinner.style.display = 'flex';
            submitButton.disabled = true;

            try {
                const formData = new FormData(form);
                const response = await fetch("{{ route('employee-pairs.store') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                    },
                    body: formData,
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Something went wrong while processing the file.');
                }

                if (!data.winner) {
                    summary.textContent = 'No two employees ever worked together on a shared project.';
                    return;
                }

                summary.textContent =
                    `Employees ${data.winner.empA} and ${data.winner.empB} worked together the longest: ${data.winner.totalDays} days total.`;

                data.winner.breakdown.forEach((row) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        `<td>${data.winner.empA}</td><td>${data.winner.empB}</td><td>${row.projectId}</td><td>${row.days}</td>`;
                    tbody.appendChild(tr);
                });

                table.style.display = data.winner.breakdown.length ? 'table' : 'none';
            } catch (err) {
                errorBox.textContent = err.message;
                errorBox.style.display = 'block';
            } finally {
                spinner.style.display = 'none';
                submitButton.disabled = false;
            }
        });
    </script>
</body>
</html>

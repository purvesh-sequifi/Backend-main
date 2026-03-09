<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>payroll failed </title>
</head>
<body>
    <h2><center> payroll failed on {{$domainName}} server | {{ @$data->usersdata->first_name }} {{ @$data->usersdata->last_name }} {{ @$data->user_id }} </center></h2>
    <h2><center> {{ date('m/d/Y', strtotime($startDate)) }} {{ date('m/d/Y', strtotime($endDate)) }} </center></h2>
    <br>
    <p>
        error :
        <br>
        <pre> <?php print_r($error); ?></pre>
    </p>
</body>
</html>
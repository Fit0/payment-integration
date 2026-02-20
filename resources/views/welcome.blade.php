<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->

    <!-- Styles -->
    <style>

    </style>
</head>

<body class="font-sans antialiased">
    <div class="container">
        <div class="row justify-content-center">
            <form action="/deposit" method="post">
                @csrf
                <div class="form-group">
                    <label for="name">Payment method</label>
                    <select class="form-control" id="pay-method" name="pay-method">
                        <option value="easymoney">EasyMoney</option>
                        <option value="superwalletz">SuperWalletz</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="text" class="form-control" id="amount" name="amount" placeholder="Insert amount"
                        value="25">
                </div>
                <div class="form-group">
                    <label for="currency">Currency</label>
                    <select class="form-control" id="currency" name="currency">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Deposit</button>
                </div>
            </form>
        </div>
    </div>
    <style>
        .container {
            width: 100%;
            font-family: sans-serif;
            max-width: 960px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            margin-bottom: 5px;
            display: inline-block;
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            color: #212529;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            box-sizing: border-box;
        }

        .form-control:focus {
            color: #212529;
            background-color: #fff;
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .btn {
            cursor: pointer;
            display: inline-block;
            font-weight: 400;
            color: #ddd;
            text-align: center;
            vertical-align: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            background-color: gray;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1.4rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .btn:hover {
            color: #212529;
            text-decoration: none;
        }
    </style>

</html>

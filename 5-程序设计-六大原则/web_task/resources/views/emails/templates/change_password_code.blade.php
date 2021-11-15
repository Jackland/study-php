<style>
    body {
        background-color: #f2f2f2;
        padding: 32px 0;
    }

    .container {
        max-width: 800px;
        padding: 40px 40px 56px 40px;
        background-color: #fff;
        margin: 0 auto;
    }

    .container hr {
        height: 0;
        border: 1px solid #eee;
        margin-bottom: 24px;
    }

    .container p {
        margin-bottom: 24px;
        font-size: 14px;
        font-family: "Microsoft YaHei";
    }

    .container .logo {
        height: 40px;
    }
</style>

<div class="container">
    <p>
        <img src="https://www.gigab2b.com/image/catalog/Logo/logo-s.png" alt="GIGACLOUD TECHNOLOGY" class="logo">
    </p>
    <hr>
    <p>To authenticate, please use the following verification code:</p>
    <p style="font-family: Helvetica; font-weight: bold; font-size: 24px">{{ $code }}</p>
    <p>
        Don't share this code with anyone. Our customer service team will never ask you for your password, verification code, credit card, or banking
        info. We hope to see you again soon.
    </p>
    <p style="margin-bottom: 0">This email is sent by the system, please do not reply.</p>
</div>
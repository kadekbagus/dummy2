<html>
    <head>
        <meta charset="utf-8" />
        <title>500</title>
    </head>
<body>
<div class="page-err err-500">
    <div class="err-container">
        <div class="text-center">
            <div class="err-status">
                 <h1>500</h1>
            </div>
            <div class="err-message">
                <h2>Something's wrong with the server</h2>
            </div>
            <div class="err-body">
                <a href="#" onclick="goback()" class="btn btn-lg btn-goback btn-info">
                    <span class="glyphicon glyphicon-home"></span>
                    <span class="space"></span>
                    Go Back to Previous Page
                </a>
            </div>
        </div>
    </div>
    <div class="footer text-center">
        Orbit v{{ ORBIT_APP_VERSION }}
    </div>
</div>
<script>
    function goback() {
        window.history.back();
    }
</script>
</body>
</html>


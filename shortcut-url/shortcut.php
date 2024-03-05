<?php
    
    $name = $_GET['name'];
    $redirectUrl = 'https://qflow.quadringroup.com/redirect-url/'.$_GET['id'];
    $Shortcut = "
        <html>
            <head>
                <title></title>
                <style>
                    .loader {
                      border: 16px solid #f3f3f3; /* Light grey */
                      border-top: 16px solid #3498db; /* Blue */
                      border-radius: 50%;
                      width: 120px;
                      height: 120px;
                      animation: spin 2s linear infinite;
                    }

                    @keyframes spin {
                      0% { transform: rotate(0deg); }
                      100% { transform: rotate(360deg); }
                    }
                </style>
            </head>
            <body>
                <a id='clickLink' href='".$redirectUrl."' class='loader'></a>
            </body>
            <script type='text/javascript'>
            // document.getElementById('clickLink').click();
            window.location.href = '".$redirectUrl."';
            </script>
        </html>
    ";  
    header("Content-type: application/octet-stream");  
    header("Content-Disposition: attachment; filename=".$name.".html;");  
    echo $Shortcut; 

?>
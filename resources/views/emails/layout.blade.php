<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #1a1a2e; padding: 30px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .header p { color: #a0a0c0; margin: 5px 0 0; font-size: 13px; }
        .body { padding: 30px; color: #333; line-height: 1.6; }
        .btn { display: inline-block; background: #e63946; color: #fff !important; padding: 12px 28px; border-radius: 5px; text-decoration: none; font-weight: bold; margin: 20px 0; }
        .info-box { background: #f8f9fa; border-left: 4px solid #e63946; padding: 15px 20px; border-radius: 4px; margin: 20px 0; }
        .info-box p { margin: 5px 0; font-size: 14px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #888; }
        .flag { display: flex; justify-content: center; gap: 0; margin-bottom: 8px; }
        .flag span { width: 20px; height: 8px; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 University Excellence Elite</h1>
            <p>République du Bénin — INSTI Lokossa</p>
        </div>
        <div class="body">
            @yield('content')
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} UNEXE — University Excellence Elite</p>
            <p>INSTI Lokossa, République du Bénin</p>
        </div>
    </div>
</body>
</html>
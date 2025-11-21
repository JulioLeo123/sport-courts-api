# Sport Courts API - Starter

Passos rápidos para rodar localmente (XAMPP):

1. Copie este diretório para C:\\xampp\\htdocs\\sport-courts-api
2. Crie .env a partir de .env.example e ajuste se necessário.
3. No terminal abra a pasta do projeto e rode:
   composer install
   composer dump-autoload
4. Garanta Apache e MySQL rodando no XAMPP.
5. Importe sql/01_schema.sql e sql/02_seed.sql (se tiver) para criar o DB.
6. Teste endpoints:
   - GET http://localhost/sport-courts-api/public/sports
   - GET http://localhost/sport-courts-api/public/availability?date=2025-11-20

Para testes unitários:
composer test
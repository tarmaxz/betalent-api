# API Multi-Gateway de Pagamentos

Sistema de pagamentos que processa cobranças em múltiplos gateways com fallback automático por prioridade.

## Requisitos

- Docker e Docker Compose

## Instalação

```bash
git clone <repo-url>
cd betalent-api
cp .env.example .env
docker-compose up -d --build
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate --seed
```

## Acessos

| Serviço | URL |
|---------|-----|
| API | http://localhost:8000 |
| Adminer | http://localhost:8080 |
| Gateway Mock 1 | http://localhost:3001 |
| Gateway Mock 2 | http://localhost:3002 |

**Usuários seed:**
- Admin: `admin@example.com` / `password`
- User: `user@example.com` / `password`

## Rotas

### Públicas

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/api/login` | Login (retorna token) |
| POST | `/api/transactions` | Realizar compra |
| GET | `/api/products` | Listar produtos |
| GET | `/api/products/{id}` | Detalhe do produto |

### Privadas (Bearer Token)

| Método | Rota | Role | Descrição |
|--------|------|------|-----------|
| POST | `/api/logout` | any | Logout |
| GET | `/api/me` | any | Usuário logado |
| GET | `/api/transactions` | any | Listar compras |
| GET | `/api/transactions/{id}` | any | Detalhe da compra |
| POST | `/api/transactions/{id}/refund` | admin | Reembolso |
| GET | `/api/clients` | any | Listar clientes |
| GET | `/api/clients/{id}` | any | Cliente e suas compras |
| GET | `/api/gateways` | any | Listar gateways |
| PUT | `/api/gateways/{id}` | admin | Ativar/desativar ou alterar prioridade |
| POST | `/api/gateways` | admin | Criar gateway |
| DELETE | `/api/gateways/{id}` | admin | Deletar gateway |
| GET | `/api/users` | admin | Listar usuários |
| POST | `/api/users` | admin | Criar usuário |
| GET | `/api/users/{id}` | admin | Detalhe do usuário |
| PUT | `/api/users/{id}` | admin | Atualizar usuário |
| DELETE | `/api/users/{id}` | admin | Deletar usuário |
| POST | `/api/products` | admin | Criar produto |
| PUT | `/api/products/{id}` | admin | Atualizar produto |
| DELETE | `/api/products/{id}` | admin | Deletar produto |

### Exemplo de compra

```json
POST /api/transactions

{
  "client": {
    "name": "João Silva",
    "email": "joao@example.com"
  },
  "products": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 2, "quantity": 1 }
  ],
  "payment": {
    "card_number": "5569000000006063",
    "card_holder": "JOAO SILVA",
    "card_expiry": "12/25",
    "card_cvv": "123"
  }
}
```

O valor total é calculado no backend a partir dos produtos e quantidades. O sistema tenta o gateway de maior prioridade primeiro; se falhar, tenta o próximo automaticamente.

## Comandos úteis

```bash
docker-compose exec app php artisan migrate:fresh --seed  # Recriar banco
docker-compose exec app php artisan test                   # Rodar testes
docker-compose logs -f app                                 # Ver logs
docker-compose down                                        # Parar tudo
```

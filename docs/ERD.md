# 21 GRIDS — IMFDB ERD

```mermaid
erDiagram
    USER ||--o{ SALE : processes
    SALE ||--o{ SALE_ITEM : contains
    PRODUCT ||--o{ SALE_ITEM : appears_in
    SALE ||--|| PAYMENT : has
    SALE ||--o{ SALE_AUDIT_LOG : records
    USER ||--o{ SALE_AUDIT_LOG : authorizes

    USER {
      int user_id PK
      string username UK
      string password_hash
      string full_name
      enum role
      bool is_active
    }
    PRODUCT {
      int product_id PK
      string name UK
      enum category
      decimal price
      bool is_available
    }
    SALE {
      int sale_id PK
      datetime sale_date
      decimal total_amount
      enum status
      int cashier_id FK
      enum discount_type
      decimal discount_amount
      int voided_by FK
    }
    SALE_ITEM {
      int sale_id PK,FK
      int product_id PK,FK
      int quantity
      decimal unit_price
    }
    PAYMENT {
      int payment_id PK
      int sale_id UK,FK
      enum method
      decimal amount
      string reference UK
      datetime paid_at
    }
    SALE_AUDIT_LOG {
      int audit_id PK
      int sale_id FK
      enum event_status
      datetime event_time
      string void_reason
    }

    USER ||--o{ SALE : processes
    USER ||--o{ SALE_AUDIT_LOG : authorizes
```

## Important cardinalities

- One User/Cashier can process many Sales; each sale has one cashier.
- One Sale contains many Sale Items; one Product can appear in many sale items. This resolves the Sale–Product M:N relationship.
- One Sale has exactly one Payment. The `UNIQUE` constraint on `payments.sale_id` enforces this.
- One Sale can have multiple Audit Log events, allowing the original Completed event to remain when a later Void event is recorded.

# Commercial demonstration

## Seed

Use a non-production database:

```powershell
$env:APP_ENV='development'
$env:DEMO_PASSWORD='Choose-A-Strong-Local-Demo-Password!2026'
php bin/migrate.php
php bin/seed_demo.php
```

All fictional accounts use the value supplied through `DEMO_PASSWORD`:

- `owner.demo@example.test`
- `manager.demo@example.test`
- `agent.demo@example.test`
- `accountant.demo@example.test`
- `fleet.demo@example.test`

## Scenario

1. Sign in as the rental agent and show customer and vehicle availability.
2. Create or inspect a reservation and explain server-side pricing and overlap protection.
3. Record an advance and deposit, then generate the printable contract and invoice.
4. Open the checkout inspection and show required photo positions, mileage, fuel, accessories, and signatures.
5. Show the active and late rentals on the dashboard.
6. Demonstrate a return comparison and damage/deposit retention workflow.
7. Sign in as the fleet agent to show maintenance and document alerts.
8. Sign in as the accountant to show payments, expenses, cash reconciliation, and CSV export.
9. Sign in as the owner to show cross-agency dashboard, audit-backed actions, revenue, utilization, and estimated profitability.

Demo identities, registrations, addresses, VINs, and financial records are fictional.


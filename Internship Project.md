**Internship Project**

**Multi-Tenant SaaS Management System**

**Company:** Macro Solutions Tools Ltd  
**Deadline:** 05 March  
**Team:** Aqsa, Masoom, Laiba

**What This System Is**

You are building a SaaS platform where different companies can register and use the system separately.

Example:

- Company A signs up
- Company B signs up
- Both use the same system
- But their data must never mix

Each company should feel like they have its own private system.

**Core System Features (Clear Overview)**

**Company Registration (Tenant System)**

- Company can register
- System generates unique tenant_id
- First user becomes Company Admin
- 7-day free trial auto assigned

**Authentication System**

Basic but professional:

- Register
- Login
- Logout
- Forgot password
- Password hashing
- Email validation (basic)

**Role-Based Access System**

Each company can:

- Create roles
- Assign permissions
- Assign roles to users

Permissions control:

- User management
- Reports access
- Role management
- Subscription management

**User Management**

Company Admin can:

- Add users
- Edit users
- Deactivate users
- Assign roles

User limits must depend on subscription plan.

**Subscription System**

Plans:

- Free Trial (7 days)
- Basic Plan
- Premium Plan

Each plan controls:

- Number of users allowed
- Access to advanced features
- Expiry date

When expired:

- Show warning
- Restrict access
- Grace period logic

**Feature Gating**

Some features available only for Premium.

Example:

- Advanced Reports
- Detailed Analytics
- Audit Log export

System must check:  
Plan + Permission

**Audit Logs**

Log important actions:

- Login
- User created
- Role updated
- Record deleted
- Subscription changed
- Unauthorized access attempt

Company Admin can view logs.

**Super Admin Panel**

Super Admin can:

- View all companies
- Suspend tenant
- Delete tenant
- See subscription status
- See total system statistics

Super Admin is outside tenant isolation.

**Basic Dashboard Features**

Each company dashboard should show:

- Total Users
- Active Users
- Subscription Status
- Days remaining
- Recent activities

Simple but clean UI.

**Aqsa – Tenant Architecture & Security**

You are building the backbone.

**Your Responsibilities**

**Tenant System**

- tenants table
- tenant_id linked in all major tables
- Automatic filtering of data by tenant
- Middleware to prevent cross-tenant access

**Super Admin Logic**

- Separate login for Super Admin
- Access to all tenants
- Ability to suspend tenant

**Security Basics**

Must include:

- Password hashing
- Input validation
- CSRF protection
- Login rate limiting
- Protection against manual URL tampering

**Database Structure**

Clean relationships:

- Tenant → Users
- Tenant → Roles
- Tenant → Logs
- Tenant → Subscription

No messy database design.

**Masoom – Role & Permission System**

You control who can do what.

**Your Responsibilities**

**Role System**

Tables:

- roles
- permissions
- role_permissions
- user_roles

All roles must belong to a tenant.

**Permission Middleware**

Before any action:

- Check permission
- If no permission → 403 error
- Log the attempt

No hardcoded admin checks.

**Role Management Panel**

Company Admin can:

- Create role
- Assign permissions
- Edit role
- Delete role (if safe)

**Dashboard Control**

Different dashboards:

- Admin → full access
- Manager → limited control
- Staff → basic view only

Menus must hide based on permission.

**Laiba – Subscription & Audit Logs**

You control business rules.

**Your Responsibilities**

**Subscription Engine**

- plans table
- subscriptions table
- Auto trial activation
- Expiry date calculation
- Grace period logic

**Plan Restrictions**

Examples:

- Basic → max 5 users
- Premium → unlimited users
- Basic → no advanced reports

System must block:

- Adding more users than plan allows
- Accessing restricted features

**Audit Log System**

Log:

- user_id
- tenant_id
- action
- timestamp
- IP address

Admin must see logs in dashboard.

**Basic Analytics**

Show:

- Number of users
- Login count
- Feature usage
- Subscription status

Simple charts are enough.

**🗓 20-Day Timeline (Clear Structure)**

**Day 1–3**

Full planning  
Database schema  
ER diagram  
Discuss structure together

**Day 4–7**

Tenant system + Authentication  
Role structure basic setup

**Day 8–12**

Permission system working  
Subscription engine working

**Day 13–16**

Audit logs  
Feature restrictions  
User limits  
Dashboard completion

**Day 17–18**

Integration testing  
Security testing  
Fix bugs

**Day 19**

Deployment on server

**Day 20**

Final presentation  
Each member explains their part

**What I Will Test Personally**

- Try accessing another company’s data
- Modify tenant_id in URL
- Use expired subscription
- Try accessing restricted page directly
- Try adding more users than allowed
- Try permission escalation

If system breaks, you must explain why.
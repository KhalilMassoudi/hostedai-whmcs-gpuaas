# Hosted AI GPUaaS WHMCS Module (Extended)

A WHMCS Server Provisioning Module that integrates WHMCS 
with the Hosted AI platform to sell GPU-as-a-Service (GPUaaS) 
products with automatic instance provisioning, recipe-based 
AI environments, and a full client management dashboard.

> This module extends the official [hostedai-whmcs](https://github.com/hostedai/hostedai-whmcs) 
> module with GPUaaS instance provisioning, recipe support, 
> and an enhanced client area dashboard.

---

## What's New in This Version

### Added on top of the original module:

| Feature | Description |
|---------|-------------|
| **GPU Instance Provisioning** | Automatically creates a GPU instance on Hosted AI when a client orders |
| **Recipe Support** | Each WHMCS product maps to a Hosted AI service with a pre-configured AI recipe (vLLM, Ollama, etc.) |
| **Dynamic Workspace** | Automatically detects and uses the team's workspace — no manual configuration needed |
| **Client Dashboard** | Full GPU instance management from WHMCS client area |
| **Instance Actions** | Start / Stop / Restart / Delete instance directly from WHMCS |
| **SSH Credentials** | Displays SSH host, port, username, password (show/hide) and SSH command |
| **Activity Logs** | Color-coded instance event logs in the client area |
| **Instance Details** | Shows name, status, region, instance type, IP, creation date |
| **Instance Termination** | Deletes the GPU instance before deleting the team on cancellation |

---

## How It Works
Client orders a WHMCS product

↓

Module creates a Team on Hosted AI

↓

Module detects team's workspace automatically

↓

Module creates a GPU instance using the configured service

(service determines which AI recipe/environment is pre-installed)

↓

Client logs into WHMCS → manages instance from dashboard

↓

On cancellation → instance deleted → team deleted

---

## Selling AI-Ready GPU Environments

Each WHMCS product maps to one Hosted AI **Service**.
A service has a recipe baked in — when the instance boots,
the AI environment is already running.

Example products you can offer:

| WHMCS Product | Hosted AI Service | What client gets |
|---------------|-------------------|------------------|
| GPU Cloud - Standard | Custom GPUaaS Instance | Bare Ubuntu + GPU |
| GPU Cloud - vLLM Ready | vllm-v1-tinyllama | vLLM + TinyLlama running on boot |
| GPU Cloud - Ollama | ollama0 | Ollama AI server running on boot |

---

## File Structure
modules/servers/hostedai/

├── hostedai.php          ← Main module (CreateAccount, Suspend, Terminate etc.)

├── lib/

│   ├── Helper.php        ← API wrapper + GPU instance methods + monitoring

│   └── ajax.php          ← AJAX handler (OTL login)

├── templates/

│   ├── manage.tpl        ← Enhanced client dashboard

│   └── error.tpl         ← Error template

├── assets/               ← CSS, JS, images

└── lang/english.php      ← Language strings
crons/

└── hostedai_cron.php     ← Monthly billing sync cron

---

## Installation

### 1. Upload files

```bash
# Clone the repo
git clone https://github.com/YOUR_USERNAME/hostedai-whmcs-gpuaas.git

# Copy module to WHMCS
cp -rv modules/ /path/to/whmcs/
cp crons/hostedai_cron.php /path/to/whmcs/crons/
```

### 2. Configure the Server in WHMCS
WHMCS Admin → System Settings → Servers → Add New Server

Name:     Hosted AI Server

Hostname: your-instance.hostedai.tech

Password: YOUR_X_API_KEY

Module:   hostedai

SSL:      ✅ enabled

### 3. Create a Product
WHMCS Admin → Products/Services → Create New Product

Module: hostedai

### 4. Configure Module Settings

| Setting | Description |
|---------|-------------|
| Pricing Policy | Select from Hosted AI pricing policies |
| Resource Policy | Must have your region configured (e.g. UK-3) |
| Service Policy | Must include your chosen service |
| Instance Type Policy | Controls available VM sizes |
| Image Policy | Controls available OS images |
| GPU Pool | Select from live dropdown (auto-populated) |
| vGPUs | Number of vGPUs (minimum 1) |
| Instance Type | VM size (Medium recommended for AI workloads) |
| Storage Block | Ephemeral storage (Block-100 = 100GB) |
| Service ID | UUID of the Hosted AI service (determines recipe) |
| Image Hash | OS image hash (Ubuntu 24.04) |
| Region ID | Integer region ID (e.g. 5 for UK-3 Reading) |

---

## Resource Policy Requirements

Each product requires a **Resource Policy** that has your 
region configured with Unlimited (or specific) resource access.
Hosted AI Admin Panel → Policies → Resource Policies

→ Create or edit a policy

→ Add Region: UK-3 (or your region)

→ Set Access: Unlimited

→ Assign to teams via product configoption2

---

## Cron Setup

Add to your server crontab for monthly billing:

```bash
0 0 1 * * /usr/bin/php /path/to/whmcs/crons/hostedai_cron.php
```

---

## Requirements

- WHMCS 8.x+
- PHP 7.4+
- Hosted AI platform account with API access
- API key generated from Hosted AI user panel
- At least one GPU pool available in your region

---

## Known Limitations

- GPU metrics (CPU/RAM/GPU %) not available via API for GPUaaS pods
- Billing data requires at least one completed billing cycle
- GPU pool must have available slots (error code 12120026 
  when all GPUs are occupied — not a region error)

---

## Original Module

This extends the official Hosted AI WHMCS module:
https://github.com/hostedai/hostedai-whmcs

---

## License

MIT
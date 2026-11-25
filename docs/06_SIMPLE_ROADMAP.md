# DeveloperJet - Simple Delivery Roadmap

Ye document owner ke bataye hue **simple flow** ka plan hai: client order kare, admin root login daale, **Deploy** dabaye, screen par terminal chalta dikhe, aur service client ko deliver ho jaaye. Saath mein ye bhi likha hai ki admin panel se kya hatega, kya abhi bana hua hai, aur kya aapse confirm karna baaki hai.

> **Abhi ka sach (25 Nov 2026):** order, payment, wallet, invoice, subdomain aur server assign ho jaata hai. Lekin **Deploy dabane se VPS par asli install abhi nahi hota.** SSH se install karne wala hissa band hai, kyunki install commands abhi likhe aur kisi test VPS par check nahi hue hain. Iske liye Phase 2 hai.

## 1. Aapka chaha hua flow (ek nazar mein)

| Step | Kaun | Kya hota hai |
|---|---|---|
| 1 | Client | Plan chunta hai, monthly ya yearly, pay karta hai (Razorpay ya wallet) |
| 2 | Client | Sirf web apps (Hermes Workspace, n8n, OpenClaw, Bundle) mein apna subdomain/domain de sakta hai. Na de to chalega |
| 3 | System | Order "Waiting for setup" mein aa jaata hai, admin ko email/Telegram |
| 4 | Admin | Order kholta hai. Plan, CPU/RAM, monthly/yearly, renewal date, client ka naam **sab pehle se bhara hota hai** |
| 5 | Admin | Sirf **VPS IP + root password** daalta hai (web app ho aur client ne subdomain na diya ho to subdomain bhi) |
| 6 | Admin | **Deploy** dabata hai. Screen par live terminal dikhta hai: kaunsa step chal raha hai |
| 7 | System | Install poora hone par order **Active**, client ko email, dashboard mein login details |
| 8 | Client | Dashboard mein IP, root login, app ka link (web apps) aur API key lagane ka tareeka dekhta hai |

## 2. Har product mein kya maanga jaayega

| Product | Checkout par client se | Setup par admin se | Delivery ke baad client ko |
|---|---|---|---|
| **Hermes CLI** | Kuch extra nahi | IP + root password | IP, root login, aur "apni API key aise lagaiye" wala command card |
| **Claude Code / Docker VPS** | Kuch extra nahi | IP + root password | IP, root login, getting-started commands |
| **Hermes Workspace** | Subdomain/domain (optional) | IP + root password (+ subdomain agar client ne nahi diya) | App link, root login, API key kaise lagaye |
| **n8n** | Subdomain/domain (optional) | IP + root password (+ subdomain agar client ne nahi diya) | n8n link, root login |
| **OpenClaw / AI Complete Bundle** | Subdomain/domain (optional) | IP + root password (+ subdomain) | App link, root login |

**API key ka naya rule:** checkout par API key **nahi** maangi jaayegi. Delivery ke baad client ke dashboard mein ek card dikhega: "Aapki service ready hai. Apni AI API key aise lagaiye", aur saath mein copy karne layak command. Command ka exact syntax hum Hermes ke official docs se check karke daalenge, khud se nahi banayenge.

## 3. Admin ke liye naya order screen (ek hi page)

**Upar (sirf padhne ke liye, apne aap bhara hua):** client ka naam, plan, CPU / RAM / disk, monthly ya yearly, agli renewal date, client ka diya subdomain.

**Beech mein (admin bharega):**
- VPS IP
- Root password (encrypted save hota hai, kahin dikhta nahi)
- Subdomain: sirf web apps mein, aur sirf tab jab client ne nahi diya

**Neeche:** ek bada **Deploy** button. Dabane par uske neeche **live terminal** khulega: "SSH connect... OS check... Docker install... n8n start... HTTPS... Done". Fail ho to wahi line laal dikhegi aur **Retry** button aayega.

**Hatayenge is screen se:** "Check only (dry run)" button, inventory dropdown ka alag form, "Request suspension" (ye More menu mein chala jaayega), aur lambi technical lines.

## 4. Admin sidebar: 30 se 10 item

Kuch delete nahi hoga, sirf jo roz kaam ka nahi hai wo **"More"** ke andar band ho jaayega.

| Roz dikhega | "More" ke andar chala jaayega |
|---|---|
| Dashboard | Categories, Add-ons |
| Orders | Billing & renewals, Billing operations, Subscriptions |
| Customers (User Manager) | Providers, Provider offers, Server pools, Infrastructure, Deployments log |
| Support tickets | Automation leads, Quote requests, Projects, SEO services |
| Wallet | Departments, Documentation, Doc categories |
| Payments & Invoices | Notifications, Security logs, Exports |
| Products & plans | Staff & roles |
| Servers | |
| Settings | |

## 5. Do mode: Manual aur Automatic

Settings mein ek toggle rahega: **Manual** ya **Automatic**.

**Manual (abhi yahi chalega):**
1. Order aata hai.
2. Aap supplier (DesiVPS, EricHost, Contabo, Server Broker) se plan ke size ka VPS kharidte ho.
3. Order screen par IP + root password daalte ho, Deploy dabate ho.

**Automatic (baad mein, jab aap on karoge):**
1. Aap ek **bada dedicated server** (jaise OVH) lete ho aur us par **Proxmox** lagta hai.
2. Admin panel mein us server ko ek baar add karte ho (Proxmox address + API token + IP addresses ki list).
3. Order aate hi system apne aap us bade server mein se client ke plan jitna chhota VPS bana deta hai (jaise 2 vCPU / 4 GB RAM / 40 GB disk), uspe app install karta hai aur deliver kar deta hai. **Admin ko kuch nahi karna.**
4. Bada server bhar jaaye ya IP khatam ho jaayein to system order ko Manual par daal deta hai aur aapko alert bhejta hai.

**Automatic ke liye zaroori cheezein:** dedicated server, us par Proxmox, Proxmox API token, aur **har client VPS ke liye ek alag public IP** (dedicated server ke saath extra IP block lena padta hai, jaise OVH Additional IP). Ye sab aapke account se aayega. Hum kisi supplier ka API apne se nahi maanenge.

## 6. Roadmap: kis order mein banega

### Phase 1: Panel simple karna (bina asli install ke, turant)
- Checkout se API key step hatana. Subdomain sirf web apps mein, optional.
- Client dashboard mein "API key aise lagaiye" card (product ke hisaab se).
- Order screen ek page ka (upar auto-filled details, beech mein IP/password/subdomain, neeche Deploy).
- Sidebar 10 item + "More".
- **"Mark delivered"**: jab tak auto-install nahi hai, admin khud SSH se install karke ek button se order Active kare. Client ko email aur dashboard details tab bhi milein.
- Dry run button aur purane technical text hatana.

### Phase 2: Asli install + live terminal (aapki approval aur ek test VPS chahiye)
- SSH connection (password se login, pehli baar server ki pehchaan save).
- Har product ke install script: Hermes CLI, Hermes Workspace, n8n, OpenClaw, Docker, Claude Code.
- Live terminal output order screen par (passwords aur keys output mein kabhi nahi dikhenge).
- Install poora hone par apne aap Active + client email + dashboard mein details.
- Pehle ek test VPS par har product kam se kam ek baar poora install karke dikhana, tab hi live par on.

### Phase 3: Automatic mode (Proxmox)
- Dedicated server + Proxmox add karna admin panel mein.
- Order aate hi chhota VPS bana ke Phase 2 wala install apne aap.
- Capacity aur IP khatam hone par alert, aur Manual par fallback.

## 7. Aapse confirm karna hai

1. **Root password client ko:** delivery ke baad client ke dashboard mein root password "Show" button se dikhe (aur client ko password badalne ki salah)? Ya sirf email mein ek baar bheje?
2. **Phase 2 ke liye test VPS:** ek sasta VPS (Ubuntu 24.04, 2 vCPU / 4 GB) de sakte ho jispe hum sab products install karke test karein? Aur kya aap asli install (live SSH) chalu karne ki **approval** dete ho?
3. **n8n licence:** n8n ka deploy tab tak band rahega jab tak Settings mein "n8n licensing verified" on nahi karte. license@n8n.io se jawab aaya?
4. **Automatic mode:** dedicated server kis company se loge (OVH, Hetzner, koi aur)? Extra IPs ka block milega?
5. **Hermes Workspace / OpenClaw:** inka official install tareeka (GitHub repo) kaunsa use karna hai, aur licence aapne check kiya?

## 7a. Owner ke jawab (25 Nov 2026) aur research

| Sawaal | Faisla |
|---|---|
| Root password | Client dashboard mein "Server login" box: IP, username, password (Show / Copy), SSH command aur PuTTY steps. Client ko `passwd` se password badalne ki salah. **Ho gaya (Phase 1).** |
| Client panel | KVM jaisa: reboot, reinstall jaise controls. Admin side "rebuild" button (VPS ko pehle jaisa). **Phase 2** (asli SSH chahiye). |
| Test VPS | Mil gaya. Login SSH key se hoga (password chat mein aaya tha, test ke baad badalna hai). |
| n8n licence | Owner ka faisla: setting hata do. **Ho gaya.** Dhyan rahe: n8n ka Sustainable Use License paid resale ko mana karta hai; ye risk owner ne liya hai. |
| Automatic mode | Baad mein, jab business badhega (OVH ya koi achhi company). |
| Hermes / OpenClaw | Research hua, neeche table. |

**Official install tareeke (25 Nov 2026 ko check kiye):**

| App | Official source | Licence | API key kaise lagta hai |
|---|---|---|---|
| Hermes Agent (CLI) | github.com/NousResearch/hermes-agent | MIT | `hermes config set OPENROUTER_API_KEY ...` ya `hermes model` |
| Hermes Workspace | github.com/outsourc-e/hermes-workspace (third-party, Nous Research ka nahi) | MIT | Hermes agent mein key, phir `hermes gateway restart` |
| OpenClaw | github.com/openclaw/openclaw | MIT | `openclaw onboard --auth-choice openrouter-api-key` |
| n8n | docs.n8n.io (Docker Compose) | Sustainable Use License | n8n editor mein Credentials |
| Claude Code | code.claude.com | Anthropic proprietary | `claude auth login` ya `ANTHROPIC_API_KEY` |

Doosri companies (Hostinger, Elestio) bhi API key checkout par nahi maangti; delivery ke baad panel mein login details aur key baad mein lagane ka tareeka deti hain.

## 8. Jo nahi badlega

- Payment, wallet, GST, invoice, renewal: jaise hai waise.
- Koi plan ya price apne se nahi badlega.
- Kisi supplier ka API bina aapke account aur official docs ke nahi lagega.
- Backup ki zimmedari hamari nahi (self-hosted); backup alag se sales team ke through.

# Quick Start Guide - Admin Panel Configuration

## 🚀 Get Started in 3 Steps

### Step 1: Access the Admin Panel
1. Login to the website with your TeamSpeak account
2. Your Unique ID must be: `Jv/d1+pX7/q343RrIMPTTVpob+U=`
3. Navigate to: `/admin/index.php`

### Step 2: Configure Members Page
Find the **"Members Page Groups (JSON)"** section and enter:
```json
[6, 7]
```
Replace with your group IDs. Click **"Save Config"**.

### Step 3: Configure Rank Badges
Find the **"Rank Badge Group ID Range (JSON)"** section and enter:
```json
{
  "min": 9,
  "max": 18
}
```
Replace with your rank group range. Click **"Save Config"**.

---

## ✅ Done!

Visit `/members.php` to see your changes in action.

---

## 📚 Need More Help?

- **User Guide:** See `ADMIN_PANEL_GUIDE.md`
- **Technical Docs:** See `ADMIN_PANEL_DATABASE_CONFIGURATION.md`
- **Change Summary:** See `CHANGES_SUMMARY.md`
- **Full Implementation:** See `IMPLEMENTATION_SUMMARY.md`

---

## 🔧 Common Configurations

### Show only VIP members (group 15)
```json
[15]
```

### Show staff members (groups 10, 11, 12)
```json
[10, 11, 12]
```

### Custom rank range (groups 20-30)
```json
{
  "min": 20,
  "max": 30
}
```

---

## 💡 Tips

- JSON must be valid (use https://jsonlint.com/ to validate)
- Group IDs must exist in your TeamSpeak server
- Changes take effect immediately
- Check `/members.php` to verify your changes

---

## 🎯 What You Can Configure

✅ Members page groups  
✅ Rank badge group IDs  
✅ Admin status sidebar groups  
✅ Group assignment rules  
✅ Discord login webhook  
✅ Server rules HTML  
✅ News posts  

**Everything in one place!**

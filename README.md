# AutoArchive Plugin for LimeSurvey

## 🧩 Overview

**AutoArchive** is a lifecycle management plugin for LimeSurvey that simplifies the handling of surveys from activation to deletion. 
It provides administrators with tools to manually expire, deactivate, and delete surveys in bulk, based on customizable time thresholds.

This plugin is especially useful for organizations that manage large volumes of surveys and need a streamlined way to enforce retention policies and maintain data hygiene—without having to process each survey individually.

---

## ✨ Features

- 🧹 **Bulk Lifecycle Actions**  
  Expire, deactivate, or delete multiple surveys or responses at once using intuitive views and confirmation modals.

- 📧 **Email Notifications**  
  Send customizable email alerts to survey owners when surveys are approaching expiration or deactivation.

- 🗃️ **Retention Policies**  
  Identify surveys whose responses or structures are eligible for deletion after a defined retention period.

- 🧭 **Manual Control with Smart Filtering**  
  Filter surveys by status and age to take action only when needed (no automatic changes are made without admin confirmation).


---

## ⚙️ Installation

### Via ZIP dowload
1. Download ZIP from https://github.com/valentinatessarounitn/LS-AutoArchive/archive/refs/heads/main.zip and uncompress it.
2. Install plugin ZIP file via the LimeSurvey admin interface (the correct ZIP file to install is located at /releases/AutoArchive_v0.0.1.zip). 
3. Activate the plugin via the LimeSurvey admin interface.
4. Configure plugin settings under the plugin configuration panel.

### Via GIT 
1. Clone this repository to your local machine
2. Rename the folder `LS-AutoArchive-main` to `AutoArchive`
3. Copy the AutoArchive folder into the `application/plugins/` directory of your LimeSurvey installation
4. Log in to LimeSurvey as an administrator and go to `Configuration` > `Plugins`.
5. If LimeSurvey does not automatically detect the plugin, manually trigger a scan of the files from the plugin configuration page.
6. Activate the plugin via the LimeSurvey admin interface.
7. Configure plugin settings under the plugin configuration panel.

---

## 🔧 Configuration Options

| Setting | Description |
|--------|-------------|
| `Max Open Months` | Max months a survey can remain active before being considered for expiration. |
| `Max Expiration Months` | Max months a survey can remain expired before being considered for deactivation. |
| `Max Response Retention Months` | Max months to retain responses after deactivation. |
| `Max Structure Retention Months` | Max months to retain survey structure after deactivation. |
| `Warning Expiration Months` | Months after activation to warn about upcoming expiration. |
| `Warning Deactivation Months` | Months after expiration to warn about upcoming deactivation. |
| `Email Placeholders` | Fixed placeholders for dynamic email content. These are predefined and cannot be edited. Listed here for reference only. |
| `Open Surveys Message Header` | Email subject for expiration warnings. |
| `Open Surveys Message Body` | Email body for expiration warnings. |
| `Expired Surveys Message Header` | Email subject for deactivation warnings. |
| `Expired Surveys Message Body` | Email body for deactivation warnings. |

---

## 📄 License & Contact

This plugin is released under the GNU General Public License.

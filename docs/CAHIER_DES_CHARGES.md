# Cahier des Charges Détaillé — Prime Care
> En cas de conflit avec ce document, `docs/BRIEF_PROJET.md` fait autorité.

---

## 1. Présentation générale
**Prime Care** (« Suivi de soins à domicile ») est une application web progressive (PWA) éditée par **Prime Vision Solutions** permettant de coordonner 24h/24 la prise en charge à domicile d'un patient âgé et diabétique.

---

## 2. Matrice des droits et rôles (`Policy`)

Les 4 rôles sont :
- `admin` : Responsable (le fils) — administration complète, configuration, suivi médical et équipe.
- `coordinator` : Aide-soignante référente — gestion du planning, validation des pointages, saisie des soins.
- `caregiver` : Aide-soignante — pointage, saisie des mesures, médicaments, repas, notes et transmissions.
- `viewer` : Famille / Médecin — consultation seule.

| Ressource (`resource`) | Action (`action`) | `admin` | `coordinator` | `caregiver` | `viewer` |
| --- | --- | :---: | :---: | :---: | :---: |
| `dashboard` | `view` | Oui | Oui | Oui | Oui |
| `patient` | `view` | Oui | Oui | Oui | Oui |
| `patient` | `update` | Oui | Non | Non | Non |
| `patient_contacts` | `view` | Oui | Oui | Oui | Oui |
| `patient_contacts` | `create`, `update`, `delete` | Oui | Non | Non | Non |
| `users` | `view`, `create`, `update`, `toggle_status`, `view_rate` | Oui | Non | Non | Non |
| `profile` | `view`, `update` | Oui | Oui | Oui | Oui |
| `vitals` | `view` | Oui | Oui | Oui | Oui |
| `vitals` | `create`, `update` | Oui | Oui | Oui | Non |
| `vitals` | `delete`, `configure` | Oui | Non | Non | Non |
| `medications` | `view` | Oui | Oui | Oui | Oui |
| `medications` | `validate_intake` | Oui | Oui | Oui | Non |
| `medications` | `create`, `update`, `delete` | Oui | Non | Non | Non |
| `meals` | `view` | Oui | Oui | Oui | Oui |
| `meals` | `create`, `update` | Oui | Oui | Oui | Non |
| `meals` | `delete` | Oui | Non | Non | Non |
| `notes` | `view` | Oui | Oui | Oui | Oui |
| `notes` | `create`, `update` | Oui | Oui | Oui | Non |
| `notes` | `delete` | Oui | Non | Non | Non |
| `schedule` | `view` | Oui | Oui | Oui | Oui |
| `schedule` | `manage` | Oui | Oui | Non | Non |
| `timeclock` | `view` | Oui | Oui | Oui | Non |
| `timeclock` | `punch` | Oui | Oui | Oui | Non |
| `timeclock` | `validate` | Oui | Oui | Non | Non |
| `alerts` | `view` | Oui | Oui | Oui | Non |
| `alerts` | `acknowledge` | Oui | Oui | Non | Non |
| `alerts` | `configure` | Oui | Non | Non | Non |
| `documents` | `view` | Oui | Oui | Oui | Oui |
| `documents` | `create`, `delete` | Oui | Non | Non | Non |
| `reports` | `view`, `export` | Oui | Oui | Non | Oui |
| `settings` | `view`, `update` | Oui | Non | Non | Non |
| `audit` | `view` | Oui | Non | Non | Non |
| `design` | `view` | Oui | Non | Non | Non |

---

## 3. Spécifications fonctionnelles (Phase 1)

### 3.1 Comptes utilisateurs & Équipe (`users`)
Chaque membre de l'équipe possède :
- Prénom et nom (`first_name`, `last_name`)
- Adresse e-mail (`email`, unique)
- Téléphone (`phone`)
- Rôle (`role` : `admin`, `coordinator`, `caregiver`, `viewer`)
- Mot de passe haché (`password_hash`) et code PIN haché 4 à 6 chiffres (`pin_hash`)
- Photo de profil (`avatar_path`)
- Couleur d'agenda/avatar (`color_hex`)
- Type de contrat (`contract_type` : CDI, CESU, Libéral, Prestataire, Famille, Médecin)
- Heures hebdomadaires contrat (`weekly_hours`)
- Taux horaire net en € (`hourly_rate`, **strictement visible et modifiable par `admin` uniquement**)
- Date d'embauche (`hired_at`)
- Contact d'urgence (`emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relation`)
- Notes administratives (`notes`)
- Préférences : thème (`theme` : `auto`, `light`, `dark`), langue (`locale` : `fr`)
- Statut actif (`is_active` : `1` ou `0`, jamais de suppression physique)

### 3.2 Fiche Patient (`patients` & `patient_contacts`)
- Identité : prénom, nom, date de naissance (âge calculé automatiquement), sexe, adresse du domicile, code d'accès / interphone, numéro de sécurité sociale masqué/informatif, photo.
- Santé : type de diabète, groupe sanguin, poids de référence, taille, objectifs glycémiques (à jeun min/max, post-prandial max, seuil hypo critique, seuil hyper critique), allergies, antécédents médicaux.
- Consignes permanentes : habitudes de vie, hydratation, mobilisation, surveillance cutanée.
- **Protocole d'hypoglycémie** : encadré critique mis en avant (signes d'alerte, resucrage immédiat 15 g de glucides rapides, contrôle à +15 min, quand appeler le 15).
- Contacts (`patient_contacts`) : médecin traitant, diabétologue, pharmacie, responsable familial, etc., avec appels directs (`tel:`), SMS (`sms:`) et e-mail (`mailto:`), plus numéros d'urgence permanents (15 SAMU, 112 Urgences).

---

## 6. Schéma de base de données (Phase 1)

Toutes les tables métier comportent : `id`, `created_at` (UTC), `updated_at` (UTC), `created_by`, `deleted_at` (UTC, nullable).
Les tables liées au patient comportent `patient_id`.
- `migrations` : suivi des migrations exécutées.
- `users` : comptes utilisateurs et membres de l'équipe.
- `remember_tokens` : jetons « rester connecté » (`selector` unique + `validator_hash` SHA-256) avec rotation.
- `password_resets` : jetons de réinitialisation de mot de passe (expiration 1 h).
- `login_attempts` : journal des tentatives de connexion par identifiant et IP (rate limiting : 5 échecs / 15 min).
- `patients` : dossier médical et administratif du patient.
- `patient_contacts` : contacts médicaux et d'urgence rattachés à `patient_id`.
- `settings` : paramètres applicatifs clé/valeur JSON.
- `audit_logs` : traçabilité complète (`user_id`, `patient_id`, `action`, `entity_type`, `entity_id`, `before_data`, `after_data`, `ip_address`, `user_agent`, `created_at`).

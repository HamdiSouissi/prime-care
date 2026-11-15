# Registre des activités de traitement (Article 30 du RGPD) — Prime Care

> **Important :** Ce registre doit être complété et tenu à jour par le responsable de traitement déployant l'application **Prime Care**. Les mentions `[À COMPLÉTER]` ne doivent pas être laissées vides lors de la mise en exploitation réelle.

---

## Informations générales du responsable de traitement

| Champ | Valeur |
|---|---|
| **Responsable de traitement** | `[À COMPLÉTER — Nom du particulier employeur ou Raison sociale]` |
| **SIRET / Identifiant CESU** | `[À COMPLÉTER]` |
| **Adresse postale** | `[À COMPLÉTER]` |
| **Délégué à la protection des données (DPO) / Référent RGPD** | `[À COMPLÉTER — Nom, e-mail, téléphone]` |
| **Hébergeur certifié HDS** | `[À COMPLÉTER — Raison sociale, adresse, référence certificat HDS]` |
| **Logiciel utilisé** | Prime Care v1.0.0 — conçu par Prime Vision Solutions |

---

## Fiche de traitement n° 1 — Suivi médical et coordination des soins à domicile

| Rubrique | Description |
|---|---|
| **Finalité principale** | Suivi quotidien de l'état de santé du patient à domicile (glycémie, constantes vitales, prises de médicaments, insuline, alimentation, soins d'hygiène, transmissions et documents médicaux) et émission d'alertes en cas d'anomalie. |
| **Base légale (Art. 6 & 9 RGPD)** | Consentement explicite du patient ou de son représentant légal (Art. 6.1.a et Art. 9.2.a du RGPD) / Médecine préventive et prise en charge sanitaire ou sociale (Art. 9.2.h du RGPD). |
| **Catégories de personnes concernées** | Patient suivi à domicile (`[À COMPLÉTER]`) ; professionnels et intervenants saisissant les actes. |
| **Catégories de données traitées** | Identité du patient, âge, pathologie chronique (diabète), constantes vitales, traitements et ordonnances, repas et hydratation, notes de liaison, photos de suivi, documents médicaux (PDF/JPG/PNG). |
| **Destinataires habilités** | Membres autorisés selon la matrice RBAC : `admin` (responsable), `coordinator` (coordinateur), `caregiver` (aide-soignante), `viewer` (lecteur médical). |
| **Transferts hors UE** | Aucun transfert hors de l'Union européenne. |
| **Durée de conservation** | Données médicales : conservées pendant toute la durée de la prise en charge (aucune purge automatique). Alertes traitées : 24 mois par défaut (configurable). |

---

## Fiche de traitement n° 2 — Gestion du planning, des pointages et des heures (RH)

| Rubrique | Description |
|---|---|
| **Finalité principale** | Planification des gardes des intervenants à domicile, enregistrement des horaires d'arrivée et de départ (pointage avec vérification ponctuelle GPS ou QR code au domicile), gestion des absences et calcul mensuel des heures travaillées (heures de nuit, dimanche, jours fériés). |
| **Base légale (Art. 6 RGPD)** | Exécution du contrat de travail (Art. 6.1.b) et obligation légale de décompte du temps de travail (Art. 6.1.c). |
| **Garanties CNIL (Géolocalisation)** | Vérification ponctuelle au seul instant du clic de pointage ; aucun suivi GPS en continu ; absence totale de blocage en cas de refus du GPS (bascule en statut « À valider » ou pointage manuel avec motif) ; notice d'information présentée avant utilisation (`/legal/attendance-notice`). |
| **Catégories de personnes concernées** | Aides-soignantes et intervenants salariés à domicile. |
| **Catégories de données traitées** | Identité, coordonnées, contact d'urgence, gardes planifiées, absences, horodatages d'arrivée/départ, distance ponctuelle au domicile lors du pointage, motif de pointage manuel. |
| **Destinataires habilités** | Administrateur (`admin`) et Coordinateur (`coordinator`) ; chaque intervenant accède uniquement à ses propres heures et pointages. |
| **Durée de conservation** | 5 ans à compter du pointage (Art. L.3245-1 du Code du travail), puis suppression logique suivie d'une suppression physique après 30 jours via la tâche `retention:purge`. |

---

## Fiche de traitement n° 3 — Sécurité, authentification et journal d'audit médico-légal

| Rubrique | Description |
|---|---|
| **Finalité principale** | Contrôle des accès (mot de passe Argon2id, code PIN sur appareil de confiance), protection contre la force brute, limitation de débit API et traçabilité immuable des créations, modifications, suppressions et exports de données. |
| **Base légale (Art. 6 RGPD)** | Obligation légale de sécurité des traitements de données de santé (Art. 6.1.c et Art. 32 du RGPD) et intérêt légitime du responsable de traitement (Art. 6.1.f). |
| **Catégories de données traitées** | Identifiant utilisateur, adresse IP, User-Agent, horodatage UTC, type d'action, valeurs avant/après modification (champs sensibles systématiquement masqués `[REDACTED]`). |
| **Destinataires habilités** | Administrateur (`admin`) uniquement, en lecture seule (`/audit`). |
| **Durée de conservation** | Journaux techniques et tentatives de connexion : 12 mois. Journal d'audit médico-légal : 6 ans. Purge automatisée logique puis physique après 30 jours via `retention:purge`. |

---

## Mesures de sécurité techniques et organisationnelles (Art. 32 RGPD)

1. **Chiffrement des secrets :** Mots de passe et codes PIN hachés avec Argon2id (`password_hash`) ; jetons d'appareils de confiance et « Se souvenir de moi » sur le modèle `selector:validator` avec hachage SHA-256 en base.
2. **Cloisonnement et contrôle d'accès :** Matrice d'autorisation centralisée (`Policy`) à 4 rôles et protection anti-IDOR sur chaque route.
3. **Protection réseau et navigateur :** En-têtes CSP stricts avec nonce aléatoire par requête, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, HSTS et cookies `__Host-` en production HTTPS.
4. **Sécurité des fichiers :** Stockage hors de `public/` (`storage/uploads/`), vérification `finfo` et en-tête `%PDF-`, ré-encodage GD supprimant les métadonnées EXIF, empreinte SHA-256 et téléchargement via contrôleur authentifié avec `Cache-Control: no-store`.
5. **Sauvegardes chiffrées :** Script `bin/backup.sh` réalisant un dump transactionnel MySQL + archive des fichiers chiffrés en `AES-256-CBC` (`-pbkdf2`) avec rotation à 30 jours et restauration vérifiée via `bin/restore.sh`.

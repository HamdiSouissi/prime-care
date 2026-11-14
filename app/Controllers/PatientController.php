<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Patient;
use App\Models\PatientContact;
use App\Services\AuditService;

/**
 * Manages the patient medical file, hypoglycemia protocol, and emergency contacts.
 */
final class PatientController extends Controller
{
    public function show(Request $request): Response
    {
        $this->authorize('patient', 'view');

        $patientModel = new Patient();
        $patient = $patientModel->findOrFail(1);

        $contactModel = new PatientContact();
        $contacts = $contactModel->forPatient(1);

        return $this->view('patient/show', [
            'pageTitle' => 'Fiche patient',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'patient' => $patient,
            'contacts' => $contacts,
            'canEdit' => Auth::can('patient', 'update'),
        ]);
    }

    public function update(Request $request): Response
    {
        $this->authorize('patient', 'update');

        $patientModel = new Patient();
        $before = $patientModel->findOrFail(1);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|min:2|max:80',
            'last_name' => 'required|min:2|max:80',
            'birth_date' => 'required|date',
            'diabetes_type' => 'required|max:80',
            'blood_group' => 'nullable|max:16',
            'reference_weight_kg' => 'nullable|numeric',
            'height_cm' => 'nullable|integer',
            'target_glycemia_fasting_min' => 'required|numeric',
            'target_glycemia_fasting_max' => 'required|numeric',
            'target_glycemia_postmeal_max' => 'required|numeric',
            'hypo_threshold_g_l' => 'required|numeric',
            'hyper_threshold_g_l' => 'required|numeric',
            'hypo_protocol' => 'required|min:10',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors(
                '/patient',
                $request->all(),
                $validator->errors(),
                'Veuillez vérifier les champs de la fiche patient.'
            );
        }

        $updates = [
            'first_name' => (string) $request->input('first_name'),
            'last_name' => (string) $request->input('last_name'),
            'birth_date' => (string) $request->input('birth_date'),
            'address' => (string) $request->input('address', ''),
            'access_code' => (string) $request->input('access_code', ''),
            'ssn_masked' => (string) $request->input('ssn_masked', ''),
            'diabetes_type' => (string) $request->input('diabetes_type'),
            'blood_group' => (string) $request->input('blood_group', ''),
            'reference_weight_kg' => $request->input('reference_weight_kg') !== '' ? (float) $request->input('reference_weight_kg') : null,
            'height_cm' => $request->input('height_cm') !== '' ? (int) $request->input('height_cm') : null,
            'target_glycemia_fasting_min' => (float) $request->input('target_glycemia_fasting_min'),
            'target_glycemia_fasting_max' => (float) $request->input('target_glycemia_fasting_max'),
            'target_glycemia_postmeal_max' => (float) $request->input('target_glycemia_postmeal_max'),
            'hypo_threshold_g_l' => (float) $request->input('hypo_threshold_g_l'),
            'hyper_threshold_g_l' => (float) $request->input('hyper_threshold_g_l'),
            'allergies' => (string) $request->input('allergies', ''),
            'medical_history' => (string) $request->input('medical_history', ''),
            'permanent_instructions' => (string) $request->input('permanent_instructions', ''),
            'hypo_protocol' => (string) $request->input('hypo_protocol'),
        ];

        $patientModel->update(1, $updates);
        AuditService::record('patient.update', 'patients', 1, $before, $updates);

        Session::flash('toast_success', 'La fiche patient a été mise à jour.');
        return Response::redirect('/patient');
    }

    public function storeContact(Request $request): Response
    {
        $this->authorize('patient_contacts', 'create');

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|min:2|max:140',
            'relation_role' => 'required|min:2|max:120',
            'category' => 'required|in:doctor,specialist,pharmacy,family,nurse,other',
            'phone' => 'required|min:2|max:32',
            'email' => 'nullable|email|max:180',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors(
                '/patient',
                $request->all(),
                $validator->errors(),
                'Veuillez vérifier les informations du contact.'
            );
        }

        $contactModel = new PatientContact();
        $data = [
            'patient_id' => 1,
            'category' => (string) $request->input('category', 'other'),
            'full_name' => (string) $request->input('full_name'),
            'relation_role' => (string) $request->input('relation_role'),
            'organization' => (string) $request->input('organization', ''),
            'phone' => (string) $request->input('phone'),
            'email' => (string) $request->input('email', ''),
            'address' => (string) $request->input('address', ''),
            'notes' => (string) $request->input('notes', ''),
            'is_emergency_priority' => $request->input('is_emergency_priority') ? 1 : 0,
            'sort_order' => (int) ($request->input('sort_order', 10) ?: 10),
        ];

        $contactId = $contactModel->create($data);
        AuditService::record('patient_contacts.create', 'patient_contacts', $contactId, null, $data);

        Session::flash('toast_success', 'Le contact « ' . $data['full_name'] . ' » a été ajouté.');
        return Response::redirect('/patient');
    }

    public function updateContact(Request $request, string $id): Response
    {
        $this->authorize('patient_contacts', 'update');

        $contactId = (int) $id;
        $contactModel = new PatientContact();
        $before = $contactModel->findOrFail($contactId);

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|min:2|max:140',
            'relation_role' => 'required|min:2|max:120',
            'category' => 'required|in:doctor,specialist,pharmacy,family,nurse,other',
            'phone' => 'required|min:2|max:32',
            'email' => 'nullable|email|max:180',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors(
                '/patient',
                $request->all(),
                $validator->errors(),
                'Veuillez corriger les informations du contact.'
            );
        }

        $updates = [
            'category' => (string) $request->input('category', $before['category']),
            'full_name' => (string) $request->input('full_name'),
            'relation_role' => (string) $request->input('relation_role'),
            'organization' => (string) $request->input('organization', ''),
            'phone' => (string) $request->input('phone'),
            'email' => (string) $request->input('email', ''),
            'address' => (string) $request->input('address', ''),
            'notes' => (string) $request->input('notes', ''),
            'is_emergency_priority' => $request->input('is_emergency_priority') ? 1 : 0,
        ];

        $contactModel->update($contactId, $updates);
        AuditService::record('patient_contacts.update', 'patient_contacts', $contactId, $before, $updates);

        Session::flash('toast_success', 'Le contact « ' . $updates['full_name'] . ' » a été modifié.');
        return Response::redirect('/patient');
    }

    public function deleteContact(Request $request, string $id): Response
    {
        $this->authorize('patient_contacts', 'delete');

        $contactId = (int) $id;
        $contactModel = new PatientContact();
        $before = $contactModel->findOrFail($contactId);

        $contactModel->softDelete($contactId);
        AuditService::record('patient_contacts.delete', 'patient_contacts', $contactId, $before, ['deleted' => true]);

        Session::flash('toast_success', 'Le contact « ' . $before['full_name'] . ' » a été supprimé.');
        return Response::redirect('/patient');
    }
}

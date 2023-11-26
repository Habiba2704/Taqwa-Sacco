<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Models\AdminNotification;
use App\Models\Loan;
use App\Models\LoanPlan;
use Illuminate\Http\Request;
use GuzzleHttp\Client;


class LoanController extends Controller {

    public function list() {
        $loans     = Loan::where('user_id', auth()->id())->with('nextInstallment')->with('plan')->orderBy('id', 'desc')->paginate(getPaginate());
        $pageTitle = 'My Loan List';
        return view($this->activeTemplate . 'user.loan.list', compact('pageTitle', 'loans'));
    }

    public function plans() {
        $pageTitle = 'Loan Plans';
        $plans     = LoanPlan::active()->latest()->get();
        return view($this->activeTemplate . 'user.loan.plans', compact('pageTitle', 'plans'));
    }

    public function applyLoan(Request $request, $id) {

        $plan = LoanPlan::active()->findOrFail($id);
        $request->validate(['amount' => "required|numeric|min:$plan->minimum_amount|max:$plan->maximum_amount"]);
        session()->put('loan', ['plan' => $plan, 'amount' => $request->amount]);
        return redirect()->route('user.loan.apply.form');
    }

    public function loanPreview() {
        $loan = session('loan');
        if (!$loan) {
            return redirect()->route('user.loan.plans');
        }
        $plan      = $loan['plan'];
        $amount    = $loan['amount'];
        $pageTitle = 'Apply For Loan';
        return view($this->activeTemplate . 'user.loan.form', compact('pageTitle', 'plan', 'amount'));
    }

    public function confirm(Request $request) {
        $loan = session('loan');
        if (!$loan) {
            return redirect()->route('user.loan.plans');
        }

        $plan   = $loan['plan'];
        $amount = $loan['amount'];
        $plan   = LoanPlan::active()->where('id', $plan->id)->firstOrFail();
        $client = new Client(); //GuzzleHttp\Client
        $url = "http://127.0.0.1:5000/prediction";
        $formData       = $plan->form->form_data;
        $formProcessor  = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $applicationForm = $formProcessor->processFormData($request, $formData);

        $user            = auth()->user();
        $per_installment = $amount * $plan->per_installment / 100;

        $percentCharge = 0;
        $charge        = $plan->fixed_charge + $percentCharge;

       
        $bank_balance = "";
        $employment_status = "";
        $annual_salary = "";
        // var_dump($applicationForm).exit();
        foreach ($applicationForm as $field) {
            $fieldName = $field['name'];
            $fieldType = $field['type'];
        
            if ($fieldName === 'Bank Balance') {
                if ($fieldType === 'text') {
                    $bank_balance = $field['value'];
                }
            } elseif ($fieldName === 'Employment Status') {
                if ($fieldType === 'checkbox' && is_array($field['value'])) {
                    $employment_status = implode(', ', $field['value']);
                }
            } elseif ($fieldName === 'Annual Salary / Income') {
                if ($fieldType === 'text') {
                    $annual_salary = $field['value'];
                }
            }
        }
        // var_dump($annual_salary).exit();
        $params = [
            "Employed"=> $bank_balance,
            "Bank Balance"=>  $employment_status,
            "Annual Salary"=> $annual_salary
            
        ];
        // $churn_params = json_encode($params);
        $headers = [
            
            'Accept' => 'application/json'
        ];
        // $header_params = json_encode($headers);

        $response = $client->request('POST', $url, [
            'headers' => $headers,
            'json'=>$params,
            'verify'  => false,
        ]);

        $responseBody = json_decode($response->getBody(),true);
        // $data =json_decode($responseBody);

        $result = $responseBody['prediction'];
        
        $churn_result = intval($result);

        $loan                         = new Loan();
        $loan->loan_number            = getTrx();
        $loan->user_id                = $user->id;
        $loan->plan_id                = $plan->id;
        $loan->amount                 = $amount;
        $loan->per_installment        = $per_installment;
        $loan->installment_interval   = $plan->installment_interval;
        $loan->delay_value            = $plan->delay_value;
        $loan->charge_per_installment = $charge;
        $loan->total_installment      = $plan->total_installment;
        $loan->application_form       = $applicationForm;
        $loan->default_prediction     = $churn_result;
        $loan->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = $user->id;
        $adminNotification->title     = 'New loan request';
        $adminNotification->click_url = urlPath('admin.loan.index') . '?search=' . $loan->loan_number;
        $adminNotification->save();

        session()->forget('loan');

        $notify[] = ['success', 'Loan application submitted successfully'];
        return redirect()->route('user.loan.list')->withNotify($notify);
    }

    public function installments($loanNumber) {
        $loan         = Loan::where('loan_number', $loanNumber)->where('user_id', auth()->id())->firstOrFail();
        $installments = $loan->installments()->paginate(getPaginate());
        $pageTitle    = 'Loan Installments';
        return view($this->activeTemplate . 'user.loan.installments', compact('pageTitle', 'installments', 'loan'));
    }
}

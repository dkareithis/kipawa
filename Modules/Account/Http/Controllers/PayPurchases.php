<?php

namespace Modules\Account\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AccountsController extends Controller
{

    /**
     * Get a collection of pay_purchases
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_pay_purchases($request)
    {
        $args = [
            'number' => (int) isset($request['per_page']) ? $request['per_page'] : 20,
            'offset' => ($request['per_page'] * ($request['page'] - 1)),
        ];

        $formatted_items   = [];
        $additional_fields = [];

        $additional_fields['namespace'] = $this->namespace;
        $additional_fields['rest_base'] = $this->rest_base;

        $pay_purchase_data = erp_acct_get_pay_purchases($args);
        $total_items       = erp_acct_get_pay_purchases(
            [
                'count'  => true,
                'number' => -1,
            ]
        );

        foreach ($pay_purchase_data as $item) {
            if (isset($request['include'])) {
                $include_params = explode(',', str_replace(' ', '', $request['include']));

                if (in_array('created_by', $include_params, true)) {
                    $item['created_by'] = $this->get_user($item['created_by']);
                }
            }

            $data              = $this->prepare_item_for_response($item, $request, $additional_fields);
            $formatted_items[] = $this->prepare_response_for_collection($data);
        }

        $response = rest_ensure_response($formatted_items);
        $response = $this->format_collection_response($response, $request, $total_items);

        $response->set_status(200);

        return $response;
    }

    /**
     * Get a pay_purchase
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_pay_purchase($request)
    {
        $id = (int) $request['id'];

        if (empty($id)) {
            return new WP_Error('rest_pay_purchase_invalid_id', __('Invalid resource id.'), ['status' => 404]);
        }

        $item = erp_acct_get_pay_purchase($id);

        $additional_fields['namespace'] = $this->namespace;
        $additional_fields['rest_base'] = $this->rest_base;

        $item = $this->prepare_item_for_response($item, $request, $additional_fields);

        $response = rest_ensure_response($item);

        $response->set_status(200);

        return $response;
    }

    /**
     * Create a pay_purchase
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function create_pay_purchase($request)
    {
        $additional_fields = [];
        $pay_purchase_data = $this->prepare_item_for_database($request);

        $items      = $request['purchase_details'];
        $item_total = [];

        foreach ($items as $key => $item) {
            $item_total[$key] = $item['line_total'];
        }

        $pay_purchase_data['amount'] = array_sum($item_total);

        $pay_purchase_data = erp_acct_insert_pay_purchase($pay_purchase_data);

        $this->add_log($pay_purchase_data, 'add');

        $additional_fields['namespace'] = $this->namespace;
        $additional_fields['rest_base'] = $this->rest_base;

        $pay_purchase_data = $this->prepare_item_for_response($pay_purchase_data, $request, $additional_fields);

        $response = rest_ensure_response($pay_purchase_data);

        $response->set_status(201);

        return $response;
    }

    /**
     * Update a pay_purchase
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function update_pay_purchase($request)
    {
        $id = (int) $request['id'];

        if (empty($id)) {
            return new WP_Error('rest_pay_purchase_invalid_id', __('Invalid resource id.'), ['status' => 404]);
        }

        $pay_purchase_data = $this->prepare_item_for_database($request);

        $items      = $request['purchase_details'];
        $item_total = [];

        foreach ($items as $key => $item) {
            $item_total[$key] = $item['line_total'];
        }

        $pay_purchase_data['amount'] = array_sum($item_total);

        $old_data = erp_acct_get_pay_purchase($id);

        $pay_purchase_id = erp_acct_update_pay_purchase($pay_purchase_data, $id);

        $this->add_log($pay_purchase_data, 'edit', $old_data);

        $response = rest_ensure_response($pay_purchase_data);
        $response = $this->format_collection_response($response, $request, 1);

        return $response;
    }

    /**
     * Void a pay_purchase
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Request
     */
    public function void_pay_purchase($request)
    {
        $id = (int) $request['id'];

        if (empty($id)) {
            return new WP_Error('rest_pay_purchase_invalid_id', __('Invalid resource id.'), ['status' => 404]);
        }

        $item = erp_acct_get_pay_purchase($id);

        erp_acct_void_pay_purchase($id);

        $this->add_log($item, 'delete');

        return new WP_REST_Response(true, 204);
    }

    /**
     * Log when purchase payment is created
     *
     * @param array $data
     * @param string $action
     * @param array $old_data
     */
    public function add_log($data, $action, $old_data = [])
    {
        switch ($action) {
            case 'edit':
                $operation = 'updated';
                $changes   = !empty($old_data) ? erp_get_array_diff($data, $old_data) : [];
                break;
            case 'delete':
                $operation = 'deleted';
                break;
            default:
                $operation = 'created';
        }

        erp_log()->add(
            [
                'component'     => 'Accounting',
                'sub_component' => __('Pay Purchase', 'erp'),
                'old_value'     => isset($changes['old_value']) ? $changes['old_value'] : '',
                'new_value'     => isset($changes['new_value']) ? $changes['new_value'] : '',
                'message'       => sprintf(__('A purchase payment of %1$s has been %2$s for %3$s', 'erp'), $data['amount'], $operation, erp_acct_get_people_name_by_people_id($data['people_id'])),
                'changetype'    => $action,
                'created_by'    => get_current_user_id(),
            ]
        );
    }

    /**
     * Prepare a single item for create or update
     *
     * @param WP_REST_Request $request request object
     *
     * @return array $prepared_item
     */
    protected function prepare_item_for_database($request)
    {
        $prepared_item = [];

        if (isset($request['vendor_id'])) {
            $prepared_item['vendor_id'] = $request['vendor_id'];
        }

        if (isset($request['ref'])) {
            $prepared_item['ref'] = $request['ref'];
        }

        if (isset($request['trn_date'])) {
            $prepared_item['trn_date'] = $request['trn_date'];
        }

        if (isset($request['purchase_details'])) {
            $prepared_item['purchase_details'] = $request['purchase_details'];
        }

        if (isset($request['amount'])) {
            $prepared_item['amount'] = $request['amount'];
        }

        if (isset($request['ref'])) {
            $prepared_item['ref'] = $request['ref'];
        }

        if (isset($request['trn_by'])) {
            $prepared_item['trn_by'] = $request['trn_by'];
        }

        if (isset($request['bank_trn_charge'])) {
            $prepared_item['bank_trn_charge'] = $request['bank_trn_charge'];
        }

        if (isset($request['particulars'])) {
            $prepared_item['particulars'] = $request['particulars'];
        }

        if (isset($request['status'])) {
            $prepared_item['status'] = $request['status'];
        }

        if (isset($request['attachments'])) {
            $prepared_item['attachments'] = maybe_serialize($request['attachments']);
        }

        if (isset($request['billing_address'])) {
            $prepared_item['billing_address'] = maybe_serialize($request['billing_address']);
        }

        if (isset($request['type'])) {
            $prepared_item['voucher_type'] = $request['type'];
        }

        if (isset($request['check_no'])) {
            $prepared_item['check_no'] = $request['check_no'];
        }

        if (isset($request['deposit_to'])) {
            $prepared_item['deposit_to'] = $request['deposit_to'];
        }

        if (isset($request['name'])) {
            $prepared_item['name'] = $request['name'];
        }

        if (isset($request['bank'])) {
            $prepared_item['bank'] = $request['bank'];
        }

        return $prepared_item;
    }

    /**
     * Prepare a single user output for response
     *
     * @param array|object    $item
     * @param WP_REST_Request $request           request object
     * @param array           $additional_fields (optional)
     *
     * @return WP_REST_Response $response response data
     */
    public function prepare_item_for_response($item, $request, $additional_fields = [])
    {
        $item = (object) $item;

        $data = [
            'id'                 => (int) $item->id,
            'voucher_no'         => (int) $item->voucher_no,
            'vendor_id'          => (int) $item->vendor_id,
            'trn_date'           => $item->trn_date,
            'purchase_details'   => $item->purchase_details,
            'pdf_link'           => $item->pdf_link,
            'ref'                =>  $item->ref,
            'amount'             => (float) $item->amount,
            'particulars'        => $item->particulars,
            'attachments'        => maybe_unserialize($item->attachments),
            'status'             => $item->status,
            'created_at'         => $item->created_at,
            'transaction_charge' => $item->transaction_charge,
            'trn_by'             => erp_acct_get_payment_method_by_id($item->trn_by)->name,
        ];

        $data = array_merge($data, $additional_fields);

        // Wrap the data in a response object
        $response = rest_ensure_response($data);

        $response = $this->add_links($response, $item, $additional_fields);

        return $response;
    }

    /**
     * Get the User's schema, conforming to JSON Schema
     *
     * @return array
     */
    public function get_item_schema()
    {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'pay_purchase',
            'type'       => 'object',
            'properties' => [
                'id'               => [
                    'description' => __('Unique identifier for the resource.'),
                    'type'        => 'integer',
                    'context'     => ['embed', 'view', 'edit'],
                    'readonly'    => true,
                ],
                'voucher_no'       => [
                    'description' => __('Voucher no. for the resource.'),
                    'type'        => 'integer',
                    'context'     => ['edit'],
                ],
                'type'             => [
                    'description' => __('Type for the resource.'),
                    'type'        => 'string',
                    'context'     => ['edit'],
                    'arg_options' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
                'vendor_id'        => [
                    'description' => __('Vendor id for the resource.'),
                    'type'        => 'integer',
                    'context'     => ['edit'],
                    'arg_options' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'required'    => true,
                ],
                'trn_date'         => [
                    'description' => __('Date for the resource.'),
                    'type'        => 'string',
                    'context'     => ['edit'],
                    'arg_options' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'required'    => true,
                ],
                'purchase_details' => [
                    'description' => __('List of line items data.', 'erp'),
                    'type'        => 'array',
                    'context'     => ['view', 'edit'],
                    'properties'  => [
                        'id'         => [
                            'description' => __('Product id.', 'erp'),
                            'type'        => 'string',
                            'context'     => ['view', 'edit'],
                        ],
                        'voucher_no' => [
                            'description' => __('Product type.', 'erp'),
                            'type'        => 'string',
                            'context'     => ['view', 'edit'],
                        ],
                        'due_date'        => [
                            'description' => __('Unit price.', 'erp'),
                            'type'        => 'integer',
                            'context'     => ['view', 'edit'],
                        ],
                        'total'      => [
                            'description' => __('Discount.', 'erp'),
                            'type'        => 'number',
                            'context'     => ['view', 'edit'],
                        ],
                        'due'      => [
                            'description' => __('Discount.', 'erp'),
                            'type'        => 'number',
                            'context'     => ['view', 'edit'],
                        ],
                        'line_total' => [
                            'description' => __('Item total.'),
                            'type'        => 'number',
                            'context'     => ['edit'],
                        ],
                    ],
                ],
                'check_no'         => [
                    'description' => __('Check no for the resource.'),
                    'type'        => 'string',
                    'context'     => ['edit'],
                    'arg_options' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
                'name'             => [
                    'description' => __('Check name for the resource.'),
                    'type'        => 'string',
                    'context'     => ['edit'],
                    'arg_options' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
                'type'            => [
                    'description' => __('Type for the resource.'),
                    'type'        => 'string',
                    'context'     => ['edit'],
                    'arg_options' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
                'status'          => [
                    'description' => __('Status for the resource.'),
                    'type'        => 'integer',
                    'context'     => ['edit'],
                ],
                'particulars'           => [
                    'description' => __('Particulars for the resource.'),
                    'type'        => 'string',
                    'context'     => ['edit'],
                    'arg_options' => [
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
                'deposit_to'      => [
                    'description' => __('Status for the resource.'),
                    'type'        => 'integer',
                    'context'     => ['edit'],
                    'required'    => true,
                ],
                'trn_by'          => [
                    'description' => __('Status for the resource.'),
                    'type'        => 'integer',
                    'context'     => ['edit'],
                    'required'    => true,
                ],
            ],
        ];

        return $schema;
    }
}

<template>
  <div class="main-content">
    <breadcumb :page="$t('AddPurchase')" :folder="$t('ListPurchases')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <!-- AI Purchase Bill Scanner Banner -->
    <b-card v-if="!isLoading" class="mb-3 border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #4338ca 0%, #6366f1 50%, #8b5cf6 100%); border-radius: 12px;">
      <div class="d-flex flex-wrap align-items-center justify-content-between p-1">
        <div class="d-flex align-items-center mb-2 mb-md-0">
          <div style="font-size: 26px; margin-right: 14px; background: rgba(255,255,255,0.22); width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
            <i class="i-Camera text-white"></i>
          </div>
          <div>
            <h5 class="mb-1 text-white font-weight-bold d-flex align-items-center">
              AI Purchase Bill Scanner
              <span class="badge badge-warning text-dark ml-2 px-2 py-1" style="font-size: 10px; font-weight: bold; border-radius: 6px;">AI POWERED</span>
            </h5>
            <small style="opacity: 0.92; font-size: 13px;">
              Upload supplier invoice image or PDF — AI will automatically extract items, costs, supplier & fill this form!
            </small>
          </div>
        </div>
        <div>
          <b-button type="button" variant="light" class="btn-pill font-weight-bold shadow-sm px-3 py-2 text-primary" @click.prevent="openAiScanModal">
            <i class="i-Scan-Barcode mr-1"></i> Scan Bill with AI
          </b-button>
        </div>
      </div>
    </b-card>

    <validation-observer ref="create_purchase" v-if="!isLoading">
      <b-form @submit.prevent="Submit_Purchase">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>

                <b-modal hide-footer id="open_scan" size="md" title="Barcode Scanner">
                  <qrcode-scanner
                    :qrbox="250" 
                    :fps="10" 
                    style="width: 100%; height: calc(100vh - 56px);"
                    @result="onScan"
                  />
                </b-modal>

                <!-- Modal: AI Purchase Bill Scanner -->
                <b-modal
                  id="ai_bill_scan_modal"
                  dialog-class="ai-bill-modal-wide"
                  size="xl"
                  hide-footer
                  title="🤖 AI Purchase Bill Scanner"
                >
                  <!-- Step 1: Upload / Scanning Screen (when no results yet) -->
                  <div v-if="!aiScan.result" class="p-2">
                    <!-- Quota Info Banner -->
                    <div class="d-flex align-items-center justify-content-between p-2 mb-3 rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                      <div class="d-flex align-items-center">
                        <i class="i-Information text-primary mr-2" style="font-size: 1.15rem;"></i>
                        <span class="small font-weight-bold text-dark">
                          Monthly AI Bill Limit: <strong>120 bills / month</strong>
                        </span>
                      </div>
                      <div>
                        <span
                          class="badge badge-pill font-weight-bold px-3 py-1"
                          :class="aiScan.quota && aiScan.quota.limit_reached ? 'badge-danger' : (aiScan.quota && aiScan.quota.remaining <= 10 ? 'badge-warning' : 'badge-primary')"
                          style="font-size: 0.85rem;"
                        >
                          Used: {{ aiScan.quota ? aiScan.quota.used : 0 }} / {{ aiScan.quota ? aiScan.quota.monthly_limit : 120 }} ({{ aiScan.quota ? aiScan.quota.remaining : 120 }} remaining)
                        </span>
                      </div>
                    </div>

                    <!-- Limit Reached Warning -->
                    <b-alert v-if="aiScan.quota && aiScan.quota.limit_reached" variant="danger" show class="mb-3">
                      <strong><i class="i-Close-Window mr-1"></i> Monthly Limit Reached:</strong>
                      Is month ki 120 bill scans ki limit poori ho chuki hai. Aap is month mazeed bills scan nahi kar saktay. Aglay month quota automatically reset ho jaye ga.
                    </b-alert>

                    <b-row>
                      <!-- Left: Upload Box -->
                      <b-col md="7" sm="12" class="pr-md-4 border-right">
                        <h6 class="font-weight-bold text-primary mb-2">1. Upload Supplier Bill / Invoice</h6>
                        <p class="text-muted small mb-3">
                          Supported formats: <strong>JPG, PNG, WEBP, PDF</strong> (up to 10MB). Mobile camera photos or scanned invoices work great!
                        </p>

                        <!-- Drag & Drop / File Input Zone -->
                        <div
                          class="ai-dropzone p-4 text-center mb-3"
                          style="border: 2px dashed #6366f1; border-radius: 12px; background: #ffffff; cursor: pointer; transition: all 0.2s;"
                          @click="$refs.aiBillFileInput.click()"
                        >
                          <input
                            type="file"
                            ref="aiBillFileInput"
                            class="d-none"
                            accept="image/*,application/pdf"
                            capture="environment"
                            @change="onAiFileSelected"
                          />

                          <div v-if="!aiScan.filePreview && !aiScan.file">
                            <div style="font-size: 48px; color: #6366f1;" class="mb-2">
                              <i class="i-Upload"></i>
                            </div>
                            <h5 class="font-weight-bold mb-1 text-dark">Click to browse or take a photo</h5>
                            <small class="text-muted">Drag & drop your receipt / invoice file here</small>
                          </div>

                          <!-- Selected File Preview -->
                          <div v-else class="text-center">
                            <div v-if="aiScan.fileType === 'image'" class="mb-2">
                              <img :src="aiScan.filePreview" alt="Bill Preview" style="max-height: 220px; max-width: 100%; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);" />
                            </div>
                            <div v-else class="mb-2">
                              <i class="i-File-Clipboard-File--Text text-danger" style="font-size: 56px;"></i>
                            </div>
                            <div class="font-weight-bold text-dark">{{ aiScan.file ? aiScan.file.name : '' }}</div>
                            <small class="text-muted d-block mb-2">{{ formatFileSize(aiScan.file ? aiScan.file.size : 0) }}</small>
                            <b-button type="button" size="sm" variant="outline-danger" class="btn-pill" @click.stop="clearAiSelectedFile">
                              Change File
                            </b-button>
                          </div>
                        </div>

                        <!-- Warehouse Check -->
                        <b-form-group :label="$t('warehouse') + ' ' + '*'" class="mb-3">
                          <v-select
                            v-model="purchase.warehouse_id"
                            :reduce="label => label.value"
                            :placeholder="$t('Choose_Warehouse')"
                            :options="warehouses.map(w => ({label: w.name, value: w.id}))"
                          />
                          <small class="text-muted" v-if="!purchase.warehouse_id">
                            Please select the warehouse where stock will be added.
                          </small>
                        </b-form-group>
                      </b-col>

                      <!-- Right: Instructions & Scan Action -->
                      <b-col md="5" sm="12" class="pl-md-4 d-flex flex-column justify-content-between">
                        <div>
                          <h6 class="font-weight-bold text-primary mb-3">2. How AI Scanning Works</h6>

                          <!-- Clean Notes Box (NO gray background) -->
                          <div class="p-3 rounded mb-4" style="border: 1px solid #e2e8f0; background: #ffffff;">
                            <div class="font-weight-bold text-dark mb-2">💡 Tips for best results:</div>
                            <ul class="pl-3 mb-0 text-muted" style="font-size: 13px; line-height: 1.8;">
                              <li>Make sure the bill or invoice photo is clear and well-lit.</li>
                              <li>Invoice number, date, vendor name, and line items will be extracted automatically.</li>
                              <li>Existing products & suppliers will be matched with your inventory.</li>
                              <li>You can review, edit quantities/prices, or add new products with 1 click!</li>
                            </ul>
                          </div>

                          <div class="d-flex align-items-center text-success mb-2" style="font-size: 13px;">
                            <i class="i-Yes mr-2 font-weight-bold"></i> Powered by OpenAI GPT Vision
                          </div>
                        </div>

                        <!-- Start Scan Button -->
                        <div class="mt-3">
                          <b-button
                            type="button"
                            variant="primary"
                            block
                            size="lg"
                            class="font-weight-bold shadow py-3"
                            style="font-size: 16px; border-radius: 10px;"
                            :disabled="!aiScan.file || aiScan.loading || (aiScan.quota && aiScan.quota.limit_reached)"
                            @click="triggerAiScan"
                          >
                            <span v-if="aiScan.loading">
                              <span class="spinner-border spinner-border-sm mr-2" role="status"></span>
                              AI is analyzing bill...
                            </span>
                            <span v-else>
                              <i class="i-Flash mr-1"></i> Start AI Scan
                            </span>
                          </b-button>
                        </div>
                      </b-col>
                    </b-row>
                  </div>

                  <!-- Step 2: Extracted Results Review Panel -->
                  <div v-else class="p-2">
                    <b-row>
                      <!-- Left: Bill Image / Document Preview -->
                      <b-col md="4" sm="12" class="border-right mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                          <h6 class="font-weight-bold mb-0 text-primary">Original Bill Preview</h6>
                          <b-button type="button" size="sm" variant="outline-primary" @click="resetAiScanner">
                            <i class="i-Upload"></i> Scan New
                          </b-button>
                        </div>
                        <div class="text-center p-2 rounded" style="max-height: 520px; overflow-y: auto; border: 1px solid #e2e8f0; background: #ffffff;">
                          <img
                            v-if="aiScan.result.bill_url && aiScan.fileType === 'image'"
                            :src="aiScan.result.bill_url"
                            alt="Bill Image"
                            style="width: 100%; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"
                          />
                          <div v-else class="p-4 text-center text-muted">
                            <i class="i-File-Clipboard-File--Text text-danger" style="font-size: 56px;"></i>
                            <div class="mt-2 font-weight-bold">{{ aiScan.result.filename }}</div>
                            <a :href="aiScan.result.bill_url" target="_blank" class="btn btn-sm btn-outline-primary mt-2">Open Document ↗</a>
                          </div>
                        </div>
                      </b-col>

                      <!-- Right: Extracted Data & Products Match Table -->
                      <b-col md="8" sm="12">
                        <!-- Header Summary Row (Clean, NO gray background) -->
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 p-3 rounded" style="border: 1px solid #e2e8f0; background: #ffffff;">
                          <div class="mr-3 mb-2 mb-md-0">
                            <small class="text-muted d-block">Supplier:</small>
                            <div class="font-weight-bold text-dark d-flex align-items-center">
                              {{ aiScan.result.supplier.name }}
                              <span v-if="aiScan.result.supplier.matched" class="badge badge-success ml-2">Matched</span>
                              <span v-else class="badge badge-warning text-dark ml-2">New Supplier</span>
                            </div>
                            <div v-if="!aiScan.result.supplier.matched" class="mt-1">
                              <b-button type="button" size="sm" variant="outline-primary" class="py-0 px-2" style="font-size: 11px;" @click="quickCreateSupplierFromAi">
                                + Quick Add Supplier
                              </b-button>
                            </div>
                          </div>

                          <div class="mr-3 mb-2 mb-md-0">
                            <small class="text-muted d-block">Bill No / Ref:</small>
                            <strong class="text-dark">{{ aiScan.result.invoice.ref || '---' }}</strong>
                          </div>

                          <div class="mr-3 mb-2 mb-md-0">
                            <small class="text-muted d-block">Bill Date:</small>
                            <strong class="text-dark">{{ aiScan.result.invoice.date || '---' }}</strong>
                          </div>

                          <div>
                            <small class="text-muted d-block">Total Amount:</small>
                            <strong class="text-success h6 mb-0">{{ currentUser.currency }} {{ (aiScan.result.financials.grand_total || 0).toFixed(2) }}</strong>
                          </div>
                        </div>

                        <!-- Items Table Header -->
                        <div class="d-flex align-items-center justify-content-between mb-2">
                          <h6 class="font-weight-bold mb-0 text-primary">
                            Extracted Products ({{ aiScan.result.items.length }} items)
                          </h6>
                          <div v-if="hasUnmatchedAiItems">
                            <b-button type="button" size="sm" variant="warning" class="text-dark font-weight-bold py-1 px-2" style="font-size: 12px;" @click="quickCreateAllUnmatchedProducts">
                              <i class="i-Add"></i> Create All {{ unmatchedAiItemsCount }} New Products
                            </b-button>
                          </div>
                        </div>

                        <!-- Items Table -->
                        <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                          <table class="table table-sm table-bordered table-hover">
                            <thead class="bg-gray-200">
                              <tr>
                                <th>Item Description</th>
                                <th>Code / Barcode</th>
                                <th style="width: 80px;">Qty</th>
                                <th style="width: 100px;">Unit Cost</th>
                                <th style="width: 100px;">Subtotal</th>
                                <th>Status</th>
                                <th style="width: 40px;"></th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="(item, idx) in aiScan.result.items" :key="'ai-item-' + idx">
                                <td>
                                  <div class="font-weight-bold">{{ item.name }}</div>
                                  <small class="text-muted" v-if="item.matched && item.extracted_name && item.extracted_name !== item.name">
                                    Receipt: {{ item.extracted_name }}
                                  </small>
                                </td>
                                <td>
                                  <small class="badge badge-light">{{ item.code || '---' }}</small>
                                </td>
                                <td>
                                  <b-form-input size="sm" type="number" v-model.number="item.quantity" min="0.01" step="any" @input="recalcAiItem(item)" />
                                </td>
                                <td>
                                  <b-form-input size="sm" type="number" v-model.number="item.cost" min="0" step="any" @input="recalcAiItem(item)" />
                                </td>
                                <td class="font-weight-bold text-dark">
                                  {{ (item.subtotal || 0).toFixed(2) }}
                                </td>
                                <td>
                                  <span v-if="item.matched" class="badge badge-success">
                                    <i class="i-Yes"></i> Matched
                                  </span>
                                  <div v-else>
                                    <span class="badge badge-warning text-dark mb-1">New Product</span>
                                    <b-button type="button" size="sm" variant="outline-primary" class="py-0 px-1 d-block" style="font-size: 11px;" @click="quickCreateProductFromAi(item, idx)">
                                      + Create
                                    </b-button>
                                  </div>
                                </td>
                                <td class="text-center">
                                  <i class="i-Close-Window text-danger cursor-pointer" title="Remove" @click="removeAiItem(idx)"></i>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>

                        <!-- Financials Summary / Footer (Clean, NO gray background) -->
                        <div class="d-flex align-items-center justify-content-between p-3 rounded mt-3" style="border-top: 1px solid #e2e8f0; background: #ffffff;">
                          <div>
                            <span class="text-muted mr-3">Discount: <strong>{{ (aiScan.result.financials.discount || 0).toFixed(2) }}</strong></span>
                            <span class="text-muted mr-3">Tax: <strong>{{ (aiScan.result.financials.tax_amount || 0).toFixed(2) }}</strong></span>
                            <span class="text-muted">Grand Total: <strong class="text-success">{{ currentUser.currency }} {{ (aiScan.result.financials.grand_total || 0).toFixed(2) }}</strong></span>
                          </div>
                          <div>
                            <b-button type="button" variant="secondary" class="mr-2 btn-pill" @click="$bvModal.hide('ai_bill_scan_modal')">Cancel</b-button>
                            <b-button type="button" variant="success" class="btn-pill font-weight-bold px-4 shadow" @click="applyAiResultsToPurchase">
                              <i class="i-Check mr-1"></i> Apply to Purchase Form
                            </b-button>
                          </div>
                        </div>
                      </b-col>
                    </b-row>
                  </div>
                </b-modal>

                <!-- Quick Add Supplier Modal -->
                <validation-observer ref="Quick_Add_Supplier_Form">
                  <b-modal hide-footer size="lg" id="Quick_Add_Supplier" :title="$t('Quick_Add_Supplier')">
                    <b-form @submit.prevent="Submit_Quick_Add_Supplier" class="quick-add-supplier-form">
                      <b-row>
                        <!-- Supplier Name -->
                        <b-col md="6" sm="12">
                          <validation-provider
                            name="Name Supplier"
                            :rules="{ required: true}"
                            v-slot="validationContext"
                          >
                            <b-form-group :label="$t('SupplierName') + ' ' + '*'">
                              <b-form-input
                                :state="getValidationState(validationContext)"
                                aria-describedby="supplier-name-feedback"
                                label="name"
                                :placeholder="$t('SupplierName')"
                                v-model="supplier.name"
                              ></b-form-input>
                              <b-form-invalid-feedback id="supplier-name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                            </b-form-group>
                          </validation-provider>
                        </b-col>

                        <!-- Supplier Email -->
                        <b-col md="6" sm="12">
                          <b-form-group :label="$t('Email')">
                            <b-form-input
                              label="email"
                              v-model="supplier.email"
                              :placeholder="$t('Email')"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- Supplier Phone -->
                        <b-col md="6" sm="12">
                          <b-form-group :label="$t('Phone')">
                            <b-form-input
                              label="Phone"
                              v-model="supplier.phone"
                              :placeholder="$t('Phone')"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- Supplier Country -->
                        <b-col md="6" sm="12">
                          <b-form-group :label="$t('Country')">
                            <b-form-input
                              label="Country"
                              v-model="supplier.country"
                              :placeholder="$t('Country')"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- Supplier City -->
                        <b-col md="6" sm="12">
                          <b-form-group :label="$t('City')">
                            <b-form-input
                              label="City"
                              v-model="supplier.city"
                              :placeholder="$t('City')"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- Supplier Tax Number -->
                        <b-col md="6" sm="12">
                          <b-form-group :label="$t('Tax_Number')">
                            <b-form-input
                              label="Tax Number"
                              v-model="supplier.tax_number"
                              :placeholder="$t('Tax_Number')"
                            ></b-form-input>
                          </b-form-group>
                        </b-col>

                        <!-- Supplier Address -->
                        <b-col md="12" sm="12">
                          <b-form-group :label="$t('Adress')">
                            <textarea
                              label="Adress"
                              class="form-control"
                              rows="4"
                              v-model="supplier.adresse"
                              :placeholder="$t('Adress')"
                            ></textarea>
                          </b-form-group>
                        </b-col>

                        <b-col md="12" class="mt-3">
                          <b-button variant="secondary" class="mr-2" @click="$bvModal.hide('Quick_Add_Supplier')">{{ $t('Cancel') }}</b-button>
                          <b-button variant="primary" type="submit" :disabled="SubmitProcessing">{{$t('submit')}}</b-button>
                          <div v-once class="typo__p" v-if="SubmitProcessing">
                            <div class="spinner sm spinner-primary mt-3"></div>
                          </div>
                        </b-col>
                      </b-row>
                    </b-form>
                  </b-modal>
                </validation-observer>

                 <!-- date  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="date"
                    :rules="{ required: true}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('date') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        aria-describedby="date-feedback"
                        type="date"
                        v-model="purchase.date"
                      ></b-form-input>
                      <b-form-invalid-feedback
                        id="date-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>
                <!-- Supplier -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Supplier" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Supplier') + ' ' + '*'">
                      <b-input-group class="category-input-group">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="purchase.supplier_id"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Supplier')"
                          :options="suppliers.map(suppliers => ({label: suppliers.name, value: suppliers.id}))"
                        />
                        <b-input-group-append
                          v-if="currentUserPermissions && currentUserPermissions.includes('Suppliers_add')"
                        >
                          <b-button
                            variant="primary"
                            @click="Quick_Add_Supplier"
                            :title="$t('Quick_Add_Supplier')"
                            class="category-add-btn"
                          >
                            <lucide-icon name="plus" />
                          </b-button>
                        </b-input-group-append>
                      </b-input-group>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- warehouse -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="warehouse" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('warehouse') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        :disabled="details.length > 0"
                        @input="Selected_Warehouse"
                        v-model="purchase.warehouse_id"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Warehouse')"
                        :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
                      />
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Product -->
                <b-col md="12" class="mb-5">
                  <h6>{{$t('ProductName')}}</h6>
                 
                  <div id="autocomplete" class="autocomplete">
                    <div class="input-with-icon">
                      <img src="/assets_setup/scan.png" alt="Scan" class="scan-icon" @click="showModal">
                    <input 
                     :placeholder="$t('Scan_Search_Product_by_Code_Name')"
                      @input='e => search_input = e.target.value' 
                      @keyup="search(search_input)"
                      @focus="handleFocus"
                      @blur="handleBlur"
                      ref="product_autocomplete"
                      class="autocomplete-input" />
                    </div>
                    <ul class="autocomplete-result-list" v-show="focused">
                      <li class="autocomplete-result" v-for="product_fil in product_filter" @mousedown="SearchProduct(product_fil)">{{getResultValue(product_fil)}}</li>
                    </ul>
                </div>
                </b-col>


                <!-- Order products  -->
                <b-col md="12">
                  <h5>{{$t('order_products')}} *</h5>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="bg-gray-300">
                        <tr>
                          <th scope="col">#</th>
                          <th scope="col">{{$t('ProductName')}}</th>
                          <th scope="col">{{$t('Net_Unit_Cost')}}</th>
                          <th scope="col">{{$t('Current_stock')}}</th>
                          <th scope="col">{{$t('Qty')}}</th>
                          <th scope="col">{{$t('Discount')}}</th>
                          <th scope="col">{{$t('Tax')}}</th>
                          <th scope="col">{{$t('SubTotal')}}</th>
                          <th scope="col" class="text-center">
                            <i class="fa fa-trash"></i>
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="details.length <=0">
                          <td colspan="9">{{$t('NodataAvailable')}}</td>
                        </tr>
                        <template v-for="detail in details">
                        <tr :key="'detail-'+detail.detail_id">
                          <td>{{detail.detail_id}}</td>
                          <td>
                            <span>{{detail.code}}</span>
                            <br>
                            <span class="badge badge-success">{{detail.name}}</span>
                            <div v-if="detail.warehouse_location" class="text-muted mt-1" style="font-size: 12px;">
                              {{ $t('Warehouse_Locations') }}: <strong>{{ detail.warehouse_location }}</strong>
                            </div>
                            <div v-if="detail.is_batch_tracked" class="text-info mt-1" style="font-size: 12px;">
                              <i class="fa fa-flask"></i> {{ $t('Track_Batches_Expiry') }}
                            </div>
                          </td>
                          <td
                          >{{currentUser.currency}} {{formatNumber(detail.Net_cost, 3)}}</td>
                          <td>
                            <span
                              class="badge badge-outline-warning"
                            >{{detail.stock}} {{detail.unitPurchase}}</span>
                          </td>
                          <td>
                            <div class="quantity">
                              <b-input-group>
                                <b-input-group-prepend>
                                  <span
                                    class="btn btn-primary btn-sm"
                                    @click="decrement(detail ,detail.detail_id)"
                                  >-</span>
                                </b-input-group-prepend>
                                <input
                                  class="form-control"
                                  @keyup="Verified_Qty(detail,detail.detail_id)"
                                  :min="0.00"
                                  v-model.number="detail.quantity"
                                >
                                <b-input-group-append>
                                  <span
                                    class="btn btn-primary btn-sm"
                                    @click="increment(detail ,detail.detail_id)"
                                  >+</span>
                                </b-input-group-append>
                              </b-input-group>
                            </div>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.DiscountNet * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.taxe * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{detail.subtotal.toFixed(2)}}</td>
                          <td>
                            <lucide-icon class="text-25 text-success" name="pencil" v-if="currentUserPermissions && currentUserPermissions.includes('edit_product_purchase')" @click="Modal_Updat_Detail(detail)" />
                            <lucide-icon class="text-25 text-danger" name="x" @click="delete_Product_Detail(detail.detail_id)" />
                          </td>
                        </tr>
                        <tr v-if="detail.is_batch_tracked" :key="'batches-'+detail.detail_id" :style="{ background: 'transparent' }">
                          <td colspan="9" :style="{ padding: '0 8px 16px 8px', border: 'none' }">
                            <div
                              :style="{
                                background: 'linear-gradient(135deg, #f0f9ff 0%, #eef2ff 100%)',
                                border: '1px solid #e0e7ff',
                                borderLeft: '4px solid #6366f1',
                                borderRadius: '10px',
                                padding: '14px 18px',
                                boxShadow: '0 1px 3px rgba(15,23,42,0.04)'
                              }"
                            >
                              <!-- Header -->
                              <div
                                :style="{
                                  display: 'flex',
                                  justifyContent: 'space-between',
                                  alignItems: 'center',
                                  marginBottom: '12px',
                                  flexWrap: 'wrap',
                                  gap: '8px'
                                }"
                              >
                                <div :style="{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }">
                                  <div
                                    :style="{
                                      width: '32px', height: '32px',
                                      borderRadius: '8px',
                                      background: '#6366f1',
                                      color: '#fff',
                                      display: 'flex',
                                      alignItems: 'center',
                                      justifyContent: 'center',
                                      boxShadow: '0 2px 6px rgba(99,102,241,0.35)'
                                    }"
                                  >
                                    <lucide-icon name="receipt-text" :style="{ fontSize: '16px' }" />
                                  </div>
                                  <div>
                                    <div :style="{ fontSize: '14px', fontWeight: '700', color: '#1e293b', lineHeight: '1.1' }">
                                      {{ $t('Batches') }}
                                    </div>
                                    <div :style="{ fontSize: '11px', color: '#64748b', marginTop: '2px' }">
                                      {{ (detail.batches || []).length }} {{ $t('items') || 'items' }}
                                      <span v-if="(detail.batches || []).length" :style="{ marginLeft: '6px' }">
                                        · {{ $t('Total') }}: <strong :style="{ color: '#0f172a' }">{{ formatNumber(batchTotalQty(detail), 2) }}</strong>
                                        / {{ formatNumber(detail.quantity || 0, 2) }}
                                      </span>
                                    </div>
                                  </div>
                                </div>
                                <button
                                  type="button"
                                  @click="add_batch(detail)"
                                  :style="{
                                    background: '#6366f1',
                                    color: '#fff',
                                    border: 'none',
                                    padding: '7px 14px',
                                    borderRadius: '8px',
                                    fontSize: '12px',
                                    fontWeight: '600',
                                    cursor: 'pointer',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: '6px',
                                    boxShadow: '0 2px 6px rgba(99,102,241,0.25)',
                                    transition: 'all 0.2s'
                                  }"
                                  onmouseover="this.style.background='#4f46e5'; this.style.transform='translateY(-1px)'"
                                  onmouseout="this.style.background='#6366f1'; this.style.transform='translateY(0)'"
                                >
                                  <lucide-icon name="plus" /> {{ $t('Add') }}
                                </button>
                              </div>

                              <!-- Batches table -->
                              <div v-if="(detail.batches || []).length" :style="{ background: '#ffffff', borderRadius: '8px', overflow: 'hidden', border: '1px solid #e2e8f0' }">
                                <table :style="{ width: '100%', marginBottom: '0', borderCollapse: 'separate', borderSpacing: '0', fontSize: '12px' }">
                                  <thead>
                                    <tr>
                                      <th :style="batchThStyle">{{ $t('Batch_No') }}</th>
                                      <th :style="batchThStyle">{{ $t('Expiry_Date') }}</th>
                                      <th :style="batchThStyle">{{ $t('Mfg_Date') }}</th>
                                      <th :style="{ ...batchThStyle, textAlign: 'right' }">{{ $t('quantity') }}</th>
                                      <th :style="{ ...batchThStyle, textAlign: 'right' }">{{ $t('cost') }}</th>
                                      <th :style="{ ...batchThStyle, width: '40px', textAlign: 'center' }"></th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr v-for="(b, bIdx) in detail.batches" :key="'b-'+detail.detail_id+'-'+bIdx" :style="{ background: bIdx % 2 === 0 ? '#ffffff' : '#fafbff' }">
                                      <td :style="batchTdStyle">
                                        <b-form-input size="sm" v-model="b.batch_no" :placeholder="$t('Batch_No')" :style="batchInputStyle" />
                                      </td>
                                      <td :style="batchTdStyle">
                                        <b-form-input size="sm" type="date" v-model="b.expiry_date" :style="batchInputStyle" />
                                      </td>
                                      <td :style="batchTdStyle">
                                        <b-form-input size="sm" type="date" v-model="b.mfg_date" :style="batchInputStyle" />
                                      </td>
                                      <td :style="batchTdStyle">
                                        <b-form-input
                                          size="sm"
                                          type="text"
                                          inputmode="decimal"
                                          lang="en"
                                          pattern="[0-9]*[.,]?[0-9]*"
                                          :value="b.qty"
                                          @input="val => onBatchNumberInput(b, 'qty', val)"
                                          :style="{ ...batchInputStyle, textAlign: 'right', fontWeight: '600' }"
                                        />
                                      </td>
                                      <td :style="batchTdStyle">
                                        <b-form-input
                                          size="sm"
                                          type="text"
                                          inputmode="decimal"
                                          lang="en"
                                          pattern="[0-9]*[.,]?[0-9]*"
                                          :value="b.unit_cost"
                                          @input="val => onBatchNumberInput(b, 'unit_cost', val)"
                                          :style="{ ...batchInputStyle, textAlign: 'right' }"
                                        />
                                      </td>
                                      <td :style="{ ...batchTdStyle, textAlign: 'center' }">
                                        <button
                                          type="button"
                                          @click="remove_batch(detail, bIdx)"
                                          :style="{
                                            width: '28px', height: '28px',
                                            borderRadius: '6px',
                                            border: '1px solid #fecaca',
                                            background: '#fef2f2',
                                            color: '#dc2626',
                                            cursor: 'pointer',
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            transition: 'all 0.2s'
                                          }"
                                          onmouseover="this.style.background='#dc2626'; this.style.color='#fff'; this.style.borderColor='#dc2626'"
                                          onmouseout="this.style.background='#fef2f2'; this.style.color='#dc2626'; this.style.borderColor='#fecaca'"
                                        >
                                          <lucide-icon name="x" />
                                        </button>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>

                              <!-- Empty state -->
                              <div
                                v-else
                                :style="{
                                  background: '#ffffff',
                                  border: '1px dashed #cbd5e1',
                                  borderRadius: '8px',
                                  padding: '24px 16px',
                                  textAlign: 'center',
                                  color: '#64748b'
                                }"
                              >
                                <div :style="{ fontSize: '24px', marginBottom: '6px', opacity: '0.5' }">
                                  <lucide-icon name="inbox" />
                                </div>
                                <div :style="{ fontSize: '13px', fontWeight: '500' }">
                                  {{ $t('NodataAvailable') }}
                                </div>
                                <div :style="{ fontSize: '11px', marginTop: '4px', opacity: '0.8' }">
                                  {{ $t('Click_Add_To_Start') || 'Click "Add" to create a batch' }}
                                </div>
                              </div>

                              <!-- Mismatch warning -->
                              <div
                                v-if="batchQtyMismatch(detail)"
                                :style="{
                                  marginTop: '10px',
                                  background: '#fef2f2',
                                  border: '1px solid #fecaca',
                                  borderLeft: '3px solid #dc2626',
                                  color: '#991b1b',
                                  padding: '8px 12px',
                                  borderRadius: '6px',
                                  fontSize: '12px',
                                  display: 'flex',
                                  alignItems: 'center',
                                  gap: '8px'
                                }"
                              >
                                <lucide-icon name="alert-triangle" :style="{ color: '#dc2626', fontSize: '14px' }" />
                                <span>{{ $t('Batch_Qty_Mismatch') }}</span>
                              </div>
                            </div>
                          </td>
                        </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                </b-col>

                <div class="offset-md-9 col-md-3 mt-4">
                  <table class="table table-striped table-sm">
                    <tbody>
                      <tr>
                        <td class="bold">{{$t('OrderTax')}}</td>
                        <td>
                          <span>{{currentUser.currency}} {{purchase.TaxNet.toFixed(2)}} ({{formatNumber(purchase.tax_rate ,2)}} %)</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Discount')}}</td>
                        <td>{{currentUser.currency}} {{purchase.discount.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Shipping')}}</td>
                        <td>{{currentUser.currency}} {{purchase.shipping.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td>
                          <span class="font-weight-bold">{{$t('Total')}}</span>
                        </td>
                        <td>
                          <span
                            class="font-weight-bold"
                          >{{currentUser.currency}} {{GrandTotal.toFixed(2)}}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                 <!-- Order Tax  -->
                <b-col lg="4" md="4" sm="12" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_purchase')">
                  <validation-provider
                    name="Order Tax"
                    :rules="{ regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('OrderTax')">
                      <b-input-group append="%">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="OrderTax-feedback"
                          label="Order Tax"
                          v-model.number="purchase.tax_rate"
                          @keyup="keyup_OrderTax()"
                        ></b-form-input>
                      </b-input-group>
                      <b-form-invalid-feedback
                        id="OrderTax-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Supplier Bill No -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <b-form-group :label="$t('Supplier_Bill_No') || 'Supplier Bill No'">
                    <b-form-input
                      v-model="purchase.supplier_bill_no"
                      :placeholder="$t('Supplier_Bill_No') || 'Supplier Bill No'"
                    ></b-form-input>
                  </b-form-group>
                </b-col>

                <!-- Supplier Bill Date -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <b-form-group :label="$t('Supplier_Bill_Date') || 'Supplier Bill Date'">
                    <b-form-input
                      type="date"
                      v-model="purchase.supplier_bill_date"
                    ></b-form-input>
                  </b-form-group>
                </b-col>

                <!-- Discount -->
                <b-col lg="4" md="4" sm="12" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_purchase')">
                  <validation-provider
                    name="Discount"
                    :rules="{ regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Discount')">
                      <b-input-group :append="currentUser.currency">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Discount-feedback"
                          label="Discount"
                          v-model.number="purchase.discount"
                          @keyup="keyup_Discount()"
                        ></b-form-input>
                      </b-input-group>
                      <b-form-invalid-feedback
                        id="Discount-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Shipping  -->
                <b-col lg="4" md="4" sm="12" class="mb-3" v-if="currentUserPermissions && currentUserPermissions.includes('edit_tax_discount_shipping_purchase')">
                  <validation-provider
                    name="Shipping"
                    :rules="{ regex: /^\d*\.?\d*$/}"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('Shipping')">
                      <b-input-group :append="currentUser.currency">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="Shipping-feedback"
                          label="Shipping"
                          v-model.number="purchase.shipping"
                          @keyup="keyup_Shipping()"
                        ></b-form-input>
                      </b-input-group>
                      <b-form-invalid-feedback
                        id="Shipping-feedback"
                      >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                 <!-- Status  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Status" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Status') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="purchase.statut"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Status')"
                        :options="
                            [
                              {label: 'received', value: 'received'},
                              {label: 'pending', value: 'pending'},
                               {label: 'ordered', value: 'ordered'}
                            ]"
                      ></v-select>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>


                <b-col md="12">
                  <b-form-group :label="$t('Note')">
                    <textarea
                      v-model="purchase.notes"
                      rows="4"
                      class="form-control"
                      :placeholder="$t('Afewwords')"
                    ></textarea>
                  </b-form-group>
                </b-col>
                <b-col md="12">
                  <b-form-group>
                    <b-button
                      variant="primary"
                      @click="Submit_Purchase"
                      :disabled="SubmitProcessing || hasBatchValidationErrors"
                      :title="hasBatchValidationErrors ? $t('Batch_Qty_Mismatch') : ''"
                    ><lucide-icon class="me-2 font-weight-bold" name="check" /> {{$t('submit')}}</b-button>
                    <div v-if="hasBatchValidationErrors" class="text-danger mt-2" style="font-size: 13px;">
                      <lucide-icon name="alert-triangle" style="vertical-align: middle; margin-right: 4px;" />
                      <template v-if="firstBatchErrorDetail && (!firstBatchErrorDetail.batches || firstBatchErrorDetail.batches.length === 0)">
                        {{ $t('Batch_Required_For_Item') || 'Add at least one batch for' }}: <strong>{{ firstBatchErrorDetail.name }}</strong>
                      </template>
                      <template v-else>
                        {{ $t('Batch_Qty_Mismatch') }}
                      </template>
                    </div>
                    <div v-once class="typo__p" v-if="SubmitProcessing">
                      <div class="spinner sm spinner-primary mt-3"></div>
                    </div>
                  </b-form-group>
                </b-col>
              </b-row>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>

    <!-- Show Modal Update Detail Product -->
    <validation-observer ref="Update_Detail_purchase">
      <b-modal hide-footer size="lg" id="form_Update_Detail" :title="detail.name">
        <b-form @submit.prevent="submit_Update_Detail">
          <b-row>
            <!-- Unit Cost -->
            <b-col lg="6" md="6" sm="12">
              <validation-provider
                name="Product Cost"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('ProductCost') + ' ' + '*'" id="cost-input">
                  <b-form-input
                    label="Product Cost"
                    v-model.number="detail.Unit_cost"
                    :state="getValidationState(validationContext)"
                    aria-describedby="cost-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="cost-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Tax Method -->
             <b-col lg="6" md="6" sm="12">
              <validation-provider name="Tax Method" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('TaxMethod') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="detail.tax_method"
                    :reduce="label => label.value"
                    :placeholder="$t('Choose_Method')"
                    :options="
                           [
                            {label: 'Exclusive', value: '1'},
                            {label: 'Inclusive', value: '2'}
                           ]"
                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Tax Rate -->
            <b-col lg="6" md="6" sm="12">
              <validation-provider
                name="Order Tax"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('OrderTax') + ' ' + '*'">
                  <b-input-group append="%">
                    <b-form-input
                      label="Order Tax"
                      v-model.number="detail.tax_percent"
                      :state="getValidationState(validationContext)"
                      aria-describedby="OrderTax-feedback"
                    ></b-form-input>
                  </b-input-group>
                  <b-form-invalid-feedback id="OrderTax-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Discount Method -->
             <b-col lg="6" md="6" sm="12">
              <validation-provider name="Discount Method" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Discount_Method') + ' ' + '*'">
                  <v-select
                    v-model="detail.discount_Method"
                    :reduce="label => label.value"
                    :placeholder="$t('Choose_Method')"
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    :options="
                           [
                            {label: 'Percent %', value: '1'},
                            {label: 'Fixed', value: '2'}
                           ]"
                  ></v-select>
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Discount Rate -->
            <b-col lg="6" md="6" sm="12">
              <validation-provider
                name="Discount Rate"
                :rules="{ required: true , regex: /^\d*\.?\d*$/}"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('Discount') + ' ' + '*'">
                  <b-form-input
                    label="Discount"
                    v-model.number="detail.discount"
                    :state="getValidationState(validationContext)"
                    aria-describedby="Discount-feedback"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Discount-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

             <!-- Unit Purchase -->
            <b-col lg="6" md="6" sm="12">
              <validation-provider name="Unit Purchase" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('UnitPurchase') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="detail.purchase_unit_id"
                    :placeholder="$t('Choose_Unit_Purchase')"
                    :reduce="label => label.value"
                    :options="units.map(units => ({label: units.name, value: units.id}))"
                  />
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Imei or serial numbers -->
              <b-col lg="12" md="12" sm="12" v-show="detail.is_imei">
                <b-form-group :label="$t('Add_product_IMEI_Serial_number')">
                  <b-form-input
                    label="Add_product_IMEI_Serial_number"
                    v-model="detail.imei_number"
                    :placeholder="$t('Add_product_IMEI_Serial_number')"
                  ></b-form-input>
                </b-form-group>
            </b-col>

            <b-col md="12">
              <b-form-group>
                <b-button variant="primary" type="submit" :disabled="Submit_Processing_detail">{{$t('submit')}}</b-button>
                <div v-once class="typo__p" v-if="Submit_Processing_detail">
                  <div class="spinner sm spinner-primary mt-3"></div>
                </div>
              </b-form-group>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>
  </div>
</template>


<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Create Purchase"
  },
  data() {
    return {
      focused: false,
      timer:null,
      search_input:'',
      product_filter:[],

      // ——— Inline styles for batches section ———
      batchThStyle: {
        padding: '8px 10px',
        background: '#f1f5f9',
        borderBottom: '1px solid #e2e8f0',
        fontSize: '10px',
        fontWeight: '700',
        color: '#475569',
        textTransform: 'uppercase',
        letterSpacing: '0.5px',
        textAlign: 'left'
      },
      batchTdStyle: {
        padding: '6px 10px',
        borderBottom: '1px solid #f1f5f9',
        verticalAlign: 'middle'
      },
      batchInputStyle: {
        height: '30px',
        fontSize: '12px',
        padding: '4px 8px'
      },

      isLoading: true,
      SubmitProcessing:false,
      Submit_Processing_detail:false,
      // AI Bill Scanner
      aiScan: {
        loading: false,
        configured: false,
        provider: 'openai',
        apiKey: '',
        saveKey: true,
        showKeyInput: false,
        file: null,
        filePreview: null,
        fileType: 'image',
        result: null,
        quota: {
          monthly_limit: 120,
          used: 0,
          remaining: 120,
          limit_reached: false
        }
      },
      warehouses: [],
      suppliers: [],
      supplier: {
        id: "",
        name: "",
        email: "",
        phone: "",
        country: "",
        city: "",
        tax_number: "",
        adresse: ""
      },
      products: [],
      details: [],
      units: [],
      detail: {
        quantity: "",
        discount: "",
        Unit_cost: "",
        discount_Method: "",
        tax_percent: "",
        tax_method: "",
        imei_number:"",
      },
      purchases: [],
      purchase: {
        id: "",
        date: new Date().toISOString().slice(0, 10),
        statut: "received",
        notes: "",
        supplier_bill_no: "",
        supplier_bill_date: "",
        supplier_id: "",
        warehouse_id: "",
        tax_rate: 0,
        TaxNet: 0,
        shipping: 0,
        discount: 0
      },
      total: 0,
      GrandTotal: 0,
      product: {
        id: "",
        code: "",
        stock: "",
        quantity: 1,
        discount: "",
        DiscountNet: "",
        discount_Method: "",
        name: "",
        unitPurchase: "",
        purchase_unit_id:"",
        fix_stock:"",
        fix_cost:"",
        Net_cost: "",
        Unit_cost: "",
        Total_cost: "",
        subtotal: "",
        product_id: "",
        detail_id: "",
        taxe: "",
        tax_percent: "",
        tax_method: "",
        product_variant_id: "",
        is_imei: "",
        imei_number:"",
      }
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions","currentUser"]),

    // True if any batch-tracked line has missing batches or a qty mismatch
    hasBatchValidationErrors() {
      if (!Array.isArray(this.details)) return false;
      return this.details.some(d => {
        if (!d || !d.is_batch_tracked) return false;
        if (!Array.isArray(d.batches) || d.batches.length === 0) return true;
        return this.batchQtyMismatch(d);
      });
    },

    // First detail that failed batch validation — used for the top-level banner
    firstBatchErrorDetail() {
      if (!Array.isArray(this.details)) return null;
      return this.details.find(d => {
        if (!d || !d.is_batch_tracked) return false;
        if (!Array.isArray(d.batches) || d.batches.length === 0) return true;
        return this.batchQtyMismatch(d);
      }) || null;
    },

    hasUnmatchedAiItems() {
      if (!this.aiScan.result || !Array.isArray(this.aiScan.result.items)) return false;
      return this.aiScan.result.items.some(i => !i.matched);
    },
    unmatchedAiItemsCount() {
      if (!this.aiScan.result || !Array.isArray(this.aiScan.result.items)) return 0;
      return this.aiScan.result.items.filter(i => !i.matched).length;
    }
  },

  methods: {

    showModal() {
      this.$bvModal.show('open_scan');
      
    },

    onScan (decodedText, decodedResult) {
      const code = decodedText;
      this.search_input = code;
      this.search();
      this.$bvModal.hide('open_scan');
    },

    //--- Submit Validate Create Purchase
    Submit_Purchase() {
      // Block submission when any batch-tracked line has missing batches
      // or batch quantities that don't sum to the line quantity.
      if (this.hasBatchValidationErrors) {
        const d = this.firstBatchErrorDetail;
        const missing = d && (!Array.isArray(d.batches) || d.batches.length === 0);
        const msg = missing
          ? `${this.$t('Batch_Required_For_Item') || 'Add at least one batch for'}: ${d ? d.name : ''}`
          : this.$t('Batch_Qty_Mismatch');
        this.makeToast("danger", msg, this.$t("Failed"));
        return;
      }
      this.$refs.create_purchase.validate().then(success => {
        if (!success) {
          this.makeToast(
            "danger",
            this.$t("Please_fill_the_form_correctly"),
            this.$t("Failed")
          );
        } else {
          this.Create_Purchase();
        }
      });
    },
    //---Submit Validation Update Detail
    submit_Update_Detail() {
      this.$refs.Update_Detail_purchase.validate().then(success => {
        if (!success) {
          return;
        } else {
          this.Update_Detail();
        }
      });
    },

    //---Validate State Fields
    getValidationState({ dirty, validated, valid = null }) {
      return dirty || validated ? valid : null;
    },

    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //---------------------- get_units ------------------------------\\
    get_units(value) {
      axios
        .get("get_units?id=" + value)
        .then(({ data }) => (this.units = data));
    },

    //------ Show Modal Update Detail Product
    Modal_Updat_Detail(detail) {
      NProgress.start();
      NProgress.set(0.1);
      this.detail = {};
      this.detail.name = detail.name;
      this.get_units(detail.product_id);
      this.detail.detail_id = detail.detail_id;
      this.detail.purchase_unit_id = detail.purchase_unit_id;
      this.detail.Unit_cost = detail.Unit_cost;
      this.detail.tax_method = detail.tax_method;
      this.detail.fix_cost = detail.fix_cost;
      this.detail.fix_stock = detail.fix_stock;
      this.detail.stock = detail.stock;
      this.detail.discount_Method = detail.discount_Method;
      this.detail.discount = detail.discount;
      this.detail.quantity = detail.quantity;
      this.detail.tax_percent = detail.tax_percent;
      this.detail.is_imei = detail.is_imei;
      this.detail.imei_number = detail.imei_number;
      
      setTimeout(() => {
        NProgress.done();
        this.$bvModal.show("form_Update_Detail");
      }, 1000);
    },

     //------ Submit Update Detail Product

    Update_Detail() {
      NProgress.start();
      NProgress.set(0.1);
      this.Submit_Processing_detail = true;
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id === this.detail.detail_id) {

          // this.convert_unit();
           for(var k=0; k<this.units.length; k++){
              if (this.units[k].id == this.detail.purchase_unit_id) {
                if(this.units[k].operator == '/'){
                  this.details[i].stock       = this.detail.fix_stock  * this.units[k].operator_value;
                  this.details[i].unitPurchase    = this.units[k].ShortName;

                }else{
                  this.details[i].stock       = this.detail.fix_stock  / this.units[k].operator_value;
                  this.details[i].unitPurchase    = this.units[k].ShortName;
                }
              }
            }
                      
          this.details[i].Unit_cost = this.detail.Unit_cost;
          this.details[i].tax_percent = this.detail.tax_percent;
          this.details[i].tax_method = this.detail.tax_method;
          this.details[i].discount_Method = this.detail.discount_Method;
          this.details[i].discount = this.detail.discount;
          this.details[i].purchase_unit_id = this.detail.purchase_unit_id;
          this.details[i].imei_number = this.detail.imei_number;

          if (this.details[i].discount_Method == "2") {
            //Fixed
            this.details[i].DiscountNet = this.details[i].discount;
          } else {
            //Percentage %
            this.details[i].DiscountNet = parseFloat(
              (this.details[i].Unit_cost * this.details[i].discount) / 100
            );
          }

          if (this.details[i].tax_method == "1") {
            //Exclusive
            this.details[i].Net_cost = parseFloat(
              this.details[i].Unit_cost - this.details[i].DiscountNet
            );

            this.details[i].taxe = parseFloat(
              (this.details[i].tax_percent *
                (this.details[i].Unit_cost - this.details[i].DiscountNet)) /
                100
            );
          } else {
            //Inclusive
            this.details[i].taxe = parseFloat(
              (this.details[i].Unit_cost - this.details[i].DiscountNet) *
                (this.details[i].tax_percent / 100)
            );

            this.details[i].Net_cost = parseFloat(
              this.details[i].Unit_cost -
                this.details[i].taxe -
                this.details[i].DiscountNet
            );
          }

          this.$forceUpdate();
        }
      }
      this.Calcul_Total();

      setTimeout(() => {
        NProgress.done();
        this.Submit_Processing_detail = false;
        this.$bvModal.hide("form_Update_Detail");
      }, 1000);
    },



    handleFocus() {
      this.focused = true
    },

    handleBlur() {
      this.focused = false
    },

    // Search Products
    search(){

      if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
      }

      if (this.search_input.length < 2) {

        return this.product_filter= [];
      }
      if (this.purchase.warehouse_id != "" &&  this.purchase.warehouse_id != null) {
        this.timer = setTimeout(() => {
          const product_filter = this.products.filter(product => product.code === this.search_input || product.barcode.includes(this.search_input));
            if(product_filter.length === 1){
                this.SearchProduct(product_filter[0])
            }else{
                this.product_filter=  this.products.filter(product => {
                  return (
                    product.name.toLowerCase().includes(this.search_input.toLowerCase()) ||
                    product.code.toLowerCase().includes(this.search_input.toLowerCase()) ||
                    product.barcode.toLowerCase().includes(this.search_input.toLowerCase())
                    );
                });

                // Check if product_filter is empty and show alert
                if (this.product_filter.length <= 0) {
                  this.makeToast(
                    "warning",
                    "Product Not Found",
                    "Warning"
                  );
                }
            }
        }, 800);
      } else {
        this.makeToast(
          "warning",
          this.$t("SelectWarehouse"),
          this.$t("Warning")
        );
      }


    },
   

    // get Result Value Search Products

    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },

    // Submit Search Products

    SearchProduct(result) {
      this.product = {};
      if (
        this.details.length > 0 &&
        this.details.some(detail => detail.code === result.code)
      ) {
        this.makeToast("warning", this.$t("AlreadyAdd"), this.$t("Warning"));
        
      } else {
        this.product.code = result.code;
        this.product.quantity = 1;
        this.product.stock = result.qte_purchase;
        this.product.fix_stock = result.qte;
        this.product.product_variant_id = result.product_variant_id;
        this.Get_Product_Details(result.id, result.product_variant_id);
      }

      this.search_input= '';
      this.$refs.product_autocomplete.value = "";
      this.product_filter = [];
    },

    //---------------------- Event Select Warehouse ------------------------------\\
    Selected_Warehouse(value) {
      this.search_input= '';
      this.product_filter = [];
      this.Get_Products_By_Warehouse(value);
    },

    //------------------------------------ Get Products By Warehouse -------------------------\\

    Get_Products_By_Warehouse(id) {
      // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
      axios
        .get("get_Products_by_warehouse/" + id + "?stock=" + 0 + "&product_service=" + 0)
         .then(response => {
            this.products = response.data;
             NProgress.done();

            })
          .catch(error => {
          });
    },

    //----------------------------------------- Add product -------------------------\\
    add_product() {
      if (this.details.length > 0) {
        this.Last_Detail_id();
      } else if (this.details.length === 0) {
        this.product.detail_id = 1;
      }

      this.details.push(this.product);

      if(this.product.is_imei){
        this.Modal_Updat_Detail(this.product);
      }
    },

    //----------------------------------------- Batch helpers (pharmacy) ------------\\
    add_batch(detail) {
      if (!detail.batches) this.$set(detail, 'batches', []);
      detail.batches.push({
        batch_no: '',
        expiry_date: '',
        mfg_date: '',
        qty: detail.batches.length === 0 ? Number(detail.quantity) || 0 : 0,
        unit_cost: Number(detail.Unit_cost) || 0
      });
    },
    // Locale-proof decimal input: allow digits + one separator, accept both "." and ","
    onBatchNumberInput(batchRow, field, raw) {
      let s = (raw == null ? '' : String(raw)).replace(',', '.');
      // strip anything that isn't a digit or dot
      s = s.replace(/[^0-9.]/g, '');
      // keep only the first dot
      const firstDot = s.indexOf('.');
      if (firstDot !== -1) {
        s = s.slice(0, firstDot + 1) + s.slice(firstDot + 1).replace(/\./g, '');
      }
      // store the string so mid-typing "0." isn't lost; total helpers already coerce via Number()
      this.$set(batchRow, field, s);
    },
    remove_batch(detail, idx) {
      if (!detail.batches) return;
      detail.batches.splice(idx, 1);
    },
    batchTotalQty(detail) {
      if (!detail || !Array.isArray(detail.batches)) return 0;
      return detail.batches.reduce((s, b) => s + (Number(b.qty) || 0), 0);
    },
    batchQtyMismatch(detail) {
      if (!detail || !detail.is_batch_tracked) return false;
      if (!Array.isArray(detail.batches) || detail.batches.length === 0) return false;
      return Math.abs(this.batchTotalQty(detail) - (Number(detail.quantity) || 0)) > 0.0001;
    },

    //-----------------------------------Verified QTY ------------------------------\\
    Verified_Qty(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (isNaN(detail.quantity)) {
            this.details[i].quantity = 1;
          }
          this.Calcul_Total();
          this.$forceUpdate();
        }
      }
    },

    //-----------------------------------increment QTY ------------------------------\\

    increment(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          this.formatNumber(this.details[i].quantity++, 2);
        }
      }
      this.$forceUpdate();
      this.Calcul_Total();
    },

    //-----------------------------------decrement QTY ------------------------------\\

    decrement(detail, id) {
      for (var i = 0; i < this.details.length; i++) {
        if (this.details[i].detail_id == id) {
          if (detail.quantity - 1 > 0) {
            this.formatNumber(this.details[i].quantity--, 2);
          }
        }
      }
      this.$forceUpdate();
      this.Calcul_Total();
    },

    //------------------------------Formetted Numbers -------------------------\\
    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    //-----------------------------------------Calcul Total ------------------------------\\
    Calcul_Total() {
      this.total = 0;
      for (var i = 0; i < this.details.length; i++) {
        var tax = this.details[i].taxe * this.details[i].quantity;
        this.details[i].subtotal = parseFloat(
          this.details[i].quantity * this.details[i].Net_cost + tax
        );
        this.total = parseFloat(this.total + this.details[i].subtotal);
      }

      const total_without_discount = parseFloat(
        this.total - this.purchase.discount
      );
      this.purchase.TaxNet = parseFloat(
        (total_without_discount * this.purchase.tax_rate) / 100
      );
      this.GrandTotal = parseFloat(
        total_without_discount + this.purchase.TaxNet + this.purchase.shipping
      );

      var grand_total =  this.GrandTotal.toFixed(2);
      this.GrandTotal = parseFloat(grand_total);
    },

    //-----------------------------------Delete Detail Product ------------------------------\\
    delete_Product_Detail(id) {
      for (var i = 0; i < this.details.length; i++) {
        if (id === this.details[i].detail_id) {
          this.details.splice(i, 1);
          this.Calcul_Total();
        }
      }
    },

    //---------- keyup OrderTax
    keyup_OrderTax() {
      if (isNaN(this.purchase.tax_rate)) {
        this.purchase.tax_rate = 0;
      } else if(this.purchase.tax_rate == ''){
         this.purchase.tax_rate = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    //---------- keyup Discount

    keyup_Discount() {
      if (isNaN(this.purchase.discount)) {
        this.purchase.discount = 0;
      } else if(this.purchase.discount == ''){
         this.purchase.discount = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    //---------- keyup Shipping

    keyup_Shipping() {
      if (isNaN(this.purchase.shipping)) {
        this.purchase.shipping = 0;
      } else if(this.purchase.shipping == ''){
         this.purchase.shipping = 0;
        this.Calcul_Total();
      }else {
        this.Calcul_Total();
      }
    },

    //-----------------------------------Verified Form Validation ------------------------------\\
    verifiedForm() {
      if (this.details.length <= 0) {
        this.makeToast(
          "warning",
          this.$t("AddProductToList"),
          this.$t("Warning")
        );
        return false;
      } else {
        var count = 0;
        for (var i = 0; i < this.details.length; i++) {
          if (
            this.details[i].quantity == "" ||
            this.details[i].quantity === 0
          ) {
            count += 1;
          }
        }

        if (count > 0) {
          this.makeToast("warning", this.$t("AddQuantity"), this.$t("Warning"));
          return false;
        } else {
          return true;
        }
      }
    },

    //--------------------------------- Create Purchase -------------------------\\
    Create_Purchase() {
      if (this.verifiedForm()) {
        this.SubmitProcessing = true;
        // Start the progress bar.
        NProgress.start();
        NProgress.set(0.1);
        axios
          .post("purchases", {
            date: this.purchase.date,
            supplier_id: this.purchase.supplier_id,
            warehouse_id: this.purchase.warehouse_id,
            statut: this.purchase.statut,
            notes: this.purchase.notes,
            supplier_bill_no:   this.purchase.supplier_bill_no   || null,
            supplier_bill_date: this.purchase.supplier_bill_date || null,
            tax_rate: this.purchase.tax_rate?this.purchase.tax_rate:0,
            TaxNet: this.purchase.TaxNet?this.purchase.TaxNet:0,
            discount: this.purchase.discount?this.purchase.discount:0,
            shipping: this.purchase.shipping?this.purchase.shipping:0,
            GrandTotal: this.GrandTotal,
            details: this.details
          })
          .then(response => {
            // Complete the animation of theprogress bar.
            NProgress.done();

            this.makeToast(
              "success",
              this.$t("Successfully_Created"),
              this.$t("Success")
            );

            this.SubmitProcessing = false;
            this.$router.push({ name: "index_purchases" });
          })
          .catch(error => {
            // Complete the animation of theprogress bar.
            NProgress.done();
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            this.SubmitProcessing = false;
          });
      }
    },

    //-------------------------------- Get Last Detail Id -------------------------\\
    Last_Detail_id() {
      this.product.detail_id = 0;
      var len = this.details.length;
      this.product.detail_id = this.details[len - 1].detail_id + 1;
    },

    //---------------------------------Get Product Details ------------------------\\

    Get_Product_Details(product_id, variant_id) {
      const wid = this.purchase && this.purchase.warehouse_id ? this.purchase.warehouse_id : null;
      const url = wid
        ? `/show_product_data/${product_id}/${variant_id}/${wid}`
        : `/show_product_data/${product_id}/${variant_id}`;

      axios.get(url).then(response => {
        this.product.discount           = response.data.discount;
        this.product.DiscountNet        = response.data.DiscountNet;
        this.product.discount_Method    = response.data.discount_method;
        this.product.product_id = response.data.id;
        this.product.name = response.data.name;
        this.product.Net_cost = response.data.Net_cost;
        this.product.Unit_cost = response.data.Unit_cost;
        this.product.taxe = response.data.tax_cost;
        this.product.tax_method = response.data.tax_method;
        this.product.tax_percent = response.data.tax_percent;
        this.product.unitPurchase = response.data.unitPurchase;
        this.product.fix_cost = response.data.fix_cost;
        this.product.purchase_unit_id = response.data.purchase_unit_id;
        this.product.is_imei = response.data.is_imei;
        this.product.imei_number = '';
        this.product.is_batch_tracked = !!response.data.is_batch_tracked;
        this.$set(this.product, 'batches', []);
        this.product.warehouse_location = response.data.warehouse_location
          ? (response.data.warehouse_location.name
              ? `${response.data.warehouse_location.code} - ${response.data.warehouse_location.name}`
              : response.data.warehouse_location.code)
          : null;
        this.add_product();
        this.Calcul_Total();
      });
    },

    //------------------------------ Quick Add Supplier -------------------------\\
    Quick_Add_Supplier() {
      this.reset_Form_supplier();
      this.$bvModal.show("Quick_Add_Supplier");
    },

    reset_Form_supplier() {
      this.supplier = {
        id: "",
        name: "",
        email: "",
        phone: "",
        country: "",
        city: "",
        tax_number: "",
        adresse: ""
      };
    },

    Submit_Quick_Add_Supplier() {
      NProgress.start();
      NProgress.set(0.1);
      this.SubmitProcessing = true;
      this.$refs.Quick_Add_Supplier_Form &&
        this.$refs.Quick_Add_Supplier_Form.validate().then(success => {
          if (!success) {
            NProgress.done();
            this.SubmitProcessing = false;
            this.makeToast(
              "danger",
              this.$t("Please_fill_the_form_correctly"),
              this.$t("Failed")
            );
            return;
          }

          axios
            .post("providers", {
              name: this.supplier.name,
              email: this.supplier.email || "",
              phone: this.supplier.phone || "",
              tax_number: this.supplier.tax_number || "",
              country: this.supplier.country || "",
              city: this.supplier.city || "",
              adresse: this.supplier.adresse || ""
            })
            .then(({ data }) => {
              NProgress.done();
              this.SubmitProcessing = false;

              const newSupplier = data && data.provider ? data.provider : data;
              if (newSupplier && newSupplier.id) {
                this.suppliers.push({
                  id: newSupplier.id,
                  name: newSupplier.name,
                  phone: newSupplier.phone || ""
                });
                this.purchase.supplier_id = newSupplier.id;
              }

              this.makeToast(
                "success",
                this.$t("Successfully_Created"),
                this.$t("Success")
              );
              this.$bvModal.hide("Quick_Add_Supplier");
              this.reset_Form_supplier();
            })
            .catch(error => {
              NProgress.done();
              this.SubmitProcessing = false;
              this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            });
        });
    },

    // ==========================================
    // AI Bill Scanner Methods
    // ==========================================
    openAiScanModal() {
      this.fetchAiQuota();
      this.$bvModal.show('ai_bill_scan_modal');
    },

    fetchAiQuota() {
      axios.get('purchase-ai/config', { meta: { skipAuthRedirect: true } })
        .then(({ data }) => {
          if (data && data.quota) {
            this.aiScan.quota = data.quota;
          }
        })
        .catch(() => {});
    },

    onAiFileSelected(e) {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      this.aiScan.file = file;
      this.aiScan.fileType = file.type === 'application/pdf' ? 'pdf' : 'image';

      if (this.aiScan.fileType === 'image') {
        const reader = new FileReader();
        reader.onload = (event) => {
          this.aiScan.filePreview = event.target.result;
        };
        reader.readAsDataURL(file);
      } else {
        this.aiScan.filePreview = null;
      }
    },

    clearAiSelectedFile() {
      this.aiScan.file = null;
      this.aiScan.filePreview = null;
      if (this.$refs.aiBillFileInput) {
        this.$refs.aiBillFileInput.value = '';
      }
    },

    triggerAiScan() {
      if (!this.aiScan.file) {
        this.makeToast('warning', 'Please select a bill image or PDF to scan', 'AI Scanner');
        return;
      }

      const formData = new FormData();
      formData.append('bill_file', this.aiScan.file);
      if (this.purchase.warehouse_id) {
        formData.append('warehouse_id', this.purchase.warehouse_id);
      }

      this.aiScan.loading = true;
      NProgress.start();

      axios.post('purchase-ai/scan', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        meta: { skipAuthRedirect: true }
      })
      .then(({ data }) => {
        this.aiScan.loading = false;
        NProgress.done();

        if (data.success) {
          this.aiScan.result = data;
          this.aiScan.configured = true;
          this.aiScan.showKeyInput = false;
          if (data.quota) {
            this.aiScan.quota = data.quota;
          }

          // If supplier was matched, also select it
          if (data.supplier && data.supplier.id) {
            this.purchase.supplier_id = data.supplier.id;
          }

          this.makeToast(
            'success',
            `Bill scanned successfully! Found ${data.items ? data.items.length : 0} items.`,
            'AI Scanner'
          );
        } else {
          this.makeToast('danger', data.message || 'Failed to scan bill', 'AI Scanner Error');
        }
      })
      .catch((error) => {
        this.aiScan.loading = false;
        NProgress.done();
        const msg = (error.response && error.response.data && error.response.data.message)
          || error.message
          || 'Failed to scan bill';
        this.makeToast('danger', msg, 'AI Scanner Error');
      });
    },

    recalcAiItem(item) {
      item.subtotal = (Number(item.quantity) || 0) * (Number(item.cost) || 0);
      if (this.aiScan.result && this.aiScan.result.financials) {
        const sum = this.aiScan.result.items.reduce((s, i) => s + (Number(i.subtotal) || 0), 0);
        this.aiScan.result.financials.subtotal = sum;
        this.aiScan.result.financials.grand_total = sum - (this.aiScan.result.financials.discount || 0) + (this.aiScan.result.financials.tax_amount || 0);
      }
    },

    removeAiItem(index) {
      if (this.aiScan.result && this.aiScan.result.items) {
        this.aiScan.result.items.splice(index, 1);
        if (this.aiScan.result.financials) {
          const sum = this.aiScan.result.items.reduce((s, i) => s + (Number(i.subtotal) || 0), 0);
          this.aiScan.result.financials.subtotal = sum;
          this.aiScan.result.financials.grand_total = sum - (this.aiScan.result.financials.discount || 0) + (this.aiScan.result.financials.tax_amount || 0);
        }
      }
    },

    quickCreateProductFromAi(item, index) {
      NProgress.start();
      axios.post('purchase-ai/quick-create-product', {
        name: item.name,
        cost: item.cost,
        code: item.code || '',
        warehouse_id: this.purchase.warehouse_id || null
      }, { meta: { skipAuthRedirect: true } })
      .then(({ data }) => {
        NProgress.done();
        if (data.success && data.product) {
          item.product_id = data.product.id;
          item.code = data.product.code;
          item.unitPurchase = data.product.unitPurchase || 'pc';
          item.purchase_unit_id = data.product.purchase_unit_id || 1;
          item.matched = true;
          item.is_new = false;
          this.makeToast('success', `Product "${data.product.name}" created!`, 'Success');
        }
      })
      .catch((err) => {
        NProgress.done();
        this.makeToast('danger', 'Failed to create product', 'Error');
      });
    },

    async quickCreateAllUnmatchedProducts() {
      if (!this.aiScan.result || !this.aiScan.result.items) return;
      const unmatched = this.aiScan.result.items.filter(i => !i.matched);
      if (unmatched.length === 0) return;

      NProgress.start();
      let created = 0;
      for (const item of unmatched) {
        try {
          const { data } = await axios.post('purchase-ai/quick-create-product', {
            name: item.name,
            cost: item.cost,
            code: item.code || '',
            warehouse_id: this.purchase.warehouse_id || null
          }, { meta: { skipAuthRedirect: true } });
          if (data.success && data.product) {
            item.product_id = data.product.id;
            item.code = data.product.code;
            item.unitPurchase = data.product.unitPurchase || 'pc';
            item.purchase_unit_id = data.product.purchase_unit_id || 1;
            item.matched = true;
            item.is_new = false;
            created++;
          }
        } catch (e) {
          console.error(e);
        }
      }
      NProgress.done();
      this.makeToast('success', `Created ${created} new products!`, 'Success');
    },

    quickCreateSupplierFromAi() {
      if (!this.aiScan.result || !this.aiScan.result.supplier) return;
      const s = this.aiScan.result.supplier;

      NProgress.start();
      axios.post('purchase-ai/quick-create-supplier', {
        name: s.name,
        phone: s.phone,
        tax_number: s.tax_number,
        adresse: s.address
      }, { meta: { skipAuthRedirect: true } })
      .then(({ data }) => {
        NProgress.done();
        if (data.success && data.supplier) {
          s.id = data.supplier.id;
          s.matched = true;
          s.is_new = false;
          this.suppliers.push({ id: data.supplier.id, name: data.supplier.name, value: data.supplier.id });
          this.purchase.supplier_id = data.supplier.id;
          this.makeToast('success', `Supplier "${data.supplier.name}" created and selected!`, 'Success');
        }
      })
      .catch(() => {
        NProgress.done();
        this.makeToast('danger', 'Failed to create supplier', 'Error');
      });
    },

    applyAiResultsToPurchase() {
      if (!this.aiScan.result) return;
      const res = this.aiScan.result;

      // 1. Supplier
      if (res.supplier && res.supplier.id) {
        this.purchase.supplier_id = res.supplier.id;
      }

      // 2. Invoice Date & Supplier Bill details
      if (res.invoice) {
        if (res.invoice.date) {
          this.purchase.date = res.invoice.date;
          this.purchase.supplier_bill_date = res.invoice.date;
        }
        if (res.invoice.ref) {
          this.purchase.supplier_bill_no = res.invoice.ref;
        }
      }

      // 3. Discount, Tax, Shipping, Notes
      if (res.financials) {
        if (res.financials.discount > 0) {
          this.purchase.discount = Number(res.financials.discount);
        }
        if (res.financials.tax_rate > 0) {
          this.purchase.tax_rate = Number(res.financials.tax_rate);
        }
        if (res.financials.shipping > 0) {
          this.purchase.shipping = Number(res.financials.shipping);
        }
        if (res.financials.notes) {
          this.purchase.notes = this.purchase.notes
            ? `${this.purchase.notes}\n${res.financials.notes}`
            : res.financials.notes;
        }
      }

      // 4. Items
      const items = res.items || [];
      let added = 0;

      items.forEach(item => {
        if (item.product_id) {
          const existing = this.details.find(d => d.product_id === item.product_id);
          if (existing) {
            existing.quantity = (Number(existing.quantity) || 0) + (Number(item.quantity) || 1);
            existing.cost = Number(item.cost) || existing.cost;
            existing.Unit_cost = Number(item.cost) || existing.Unit_cost;
            existing.Net_cost = Number(item.cost) || existing.Net_cost;
          } else {
            const detail_id = this.details.length > 0
              ? Math.max(...this.details.map(d => d.detail_id)) + 1
              : 1;

            this.details.push({
              detail_id,
              product_id: item.product_id,
              product_variant_id: item.product_variant_id || null,
              name: item.name,
              code: item.code || '',
              quantity: Number(item.quantity) || 1,
              cost: Number(item.cost) || 0,
              Unit_cost: Number(item.cost) || 0,
              Net_cost: Number(item.cost) || 0,
              fix_cost: Number(item.cost) || 0,
              discount: Number(item.discount) || 0,
              DiscountNet: Number(item.discount) || 0,
              discount_Method: '2',
              tax_percent: Number(item.tax_percent) || 0,
              taxe: 0,
              tax_method: '1',
              unitPurchase: item.unitPurchase || 'pc',
              purchase_unit_id: item.purchase_unit_id || 1,
              stock: 0,
              subtotal: Number(item.subtotal) || ((Number(item.quantity) || 1) * (Number(item.cost) || 0)),
              is_batch_tracked: false,
              batches: []
            });
            added++;
          }
        }
      });

      this.Calcul_Total();
      this.$bvModal.hide('ai_bill_scan_modal');

      this.makeToast(
        'success',
        `Successfully applied bill data! ${added} item(s) added to purchase form.`,
        'AI Scanner'
      );
    },

    resetAiScanner() {
      this.aiScan.result = null;
      this.aiScan.file = null;
      this.aiScan.filePreview = null;
      if (this.$refs.aiBillFileInput) {
        this.$refs.aiBillFileInput.value = '';
      }
    },

    formatFileSize(bytes) {
      if (!bytes) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },

    //---------------------------------------Get Elements Purchase ------------------------------\\
    GetElements() {
      axios
        .get("purchases/create")
        .then(response => {
          this.suppliers = response.data.suppliers;
          this.warehouses = response.data.warehouses;
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  },

  //-----------------------------  Created function-------------------
  created() {
    this.GetElements();
  },
  mounted() {
    if (this.$route && (this.$route.query.scan || this.$route.query.ai_scan)) {
      setTimeout(() => {
        this.openAiScanModal();
      }, 400);
    }
  }
};
</script>

<style>

  .input-with-icon {
    display: flex;
    align-items: center;
  }

  .scan-icon {
    width: 50px; /* Adjust size as needed */
    height: 50px;
    margin-right: 8px; /* Adjust spacing as needed */
    cursor: pointer;
  }

  /* ===== v-select in input-group =====
     A global rule (specificity 0,4,0) sets
       .input-group:not(.input-group-sm):not(.input-group-lg) .btn { height: calc(1.5em + 0.7rem + 0px) }
     Override only the button height (with !important to beat that 0,4,0
     global selector) so the wrapped v-select lines up with its standalone
     neighbors. */
  .input-group.category-input-group {
    display: flex;
    align-items: stretch;
    flex-wrap: nowrap;
  }

  .input-group.category-input-group .v-select {
    flex: 1 1 auto;
    min-width: 0;
  }

  .input-group.category-input-group .v-select .vs__dropdown-toggle {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
  }

  .input-group.category-input-group .input-group-append {
    align-items: stretch;
  }

  .input-group.category-input-group .category-add-btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    white-space: nowrap;
    height: calc(1.5em + 0.7rem + 0px) !important;
  }

  /* AI Bill Scanner Wide Modal */
  .ai-bill-modal-wide {
    max-width: 95vw !important;
    width: 1350px !important;
  }
  @media (min-width: 1400px) {
    .ai-bill-modal-wide {
      max-width: 90vw !important;
      width: 1400px !important;
    }
  }
  .ai-bill-modal-wide .modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  }
  .ai-bill-modal-wide .modal-header {
    border-bottom: 1px solid #edf2f7;
    padding: 1.25rem 1.5rem;
    background: #ffffff;
  }
  .ai-bill-modal-wide .modal-body {
    padding: 1.5rem;
    background: #ffffff;
  }
</style>
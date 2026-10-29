<template>
  <!-- NEW FEATURE - SAFE ADDITION -->
  <div class="main-content">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-1">{{ $t('Chart_of_Accounts_Title') }}</h4>
        <div class="text-muted small">{{ $t('Chart_of_Accounts_Subtitle') }}</div>
      </div>
    </div>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div class="card wrapper" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        @on-row-dblclick="onRowDblClick"
        :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          nextLabel: $t('Next'),
          prevLabel: $t('Prev'),
          perPage: 50,
          perPageDropdown: [10, 25, 50, 100]
        }"
        styleClass="table-hover tableOne vgt-table cursor-pointer"
      >
        <div slot="table-actions" class="mt-2 mb-3 d-flex align-items-center justify-content-between flex-wrap">
          <div class="d-flex align-items-center">
            <b-button @click="openCreate()" class="btn-rounded" variant="btn btn-primary btn-icon m-1">
              <lucide-icon name="plus" />
              {{ $t('Add') }}
            </b-button>
            <select v-model="filterType" @change="onTypeChange" class="form-control form-control-sm ml-2" style="width: auto; min-width: 140px; display: inline-block;">
              <option value="">{{ $t('All_Types') || 'All Types' }}</option>
              <option value="asset">{{ $t('Asset') || 'Asset' }}</option>
              <option value="liability">{{ $t('Liability') || 'Liability' }}</option>
              <option value="equity">{{ $t('Equity') || 'Equity' }}</option>
              <option value="income">{{ $t('Income') || 'Income' }}</option>
              <option value="expense">{{ $t('Expense') || 'Expense' }}</option>
            </select>
          </div>
          <div class="text-muted small pr-3 my-1">
            <span class="badge badge-light-info p-1 px-2 d-inline-flex align-items-center" style="font-size: 12px;">
              <lucide-icon name="info" style="width: 14px; height: 14px; margin-right: 5px;" />
              Tip: Double-click any row to open its Account Ledger
            </span>
          </div>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'code'">
            <a @click.stop="openLedger(props.row)" class="text-primary font-weight-bold cursor-pointer hover-underline" :title="$t('Account_Ledger') || 'Open Ledger'">
              {{ props.row.code }}
            </a>
          </span>
          <span v-else-if="props.column.field == 'name'">
            <a @click.stop="openLedger(props.row)" class="text-dark font-weight-600 cursor-pointer hover-underline" :title="$t('Account_Ledger') || 'Open Ledger'">
              {{ props.row.name }}
            </a>
          </span>
          <span v-else-if="props.column.field == 'parent'">{{ parentName(props.row.parent_id) }}</span>
          <span v-else-if="props.column.field == 'type'">
            <span class="badge" :class="typeBadgeClass(props.row.type)">{{ (props.row.type || '').toUpperCase() }}</span>
          </span>
          <span v-else-if="props.column.field == 'balance'">
            <span :class="Number(props.row.balance) < 0 ? 'text-danger font-weight-bold' : 'text-success font-weight-bold'">
              {{ toMoney(props.row.balance) }}
            </span>
          </span>
          <span v-else-if="props.column.field == 'is_active' || props.column.field == 'active'">
            <span class="badge" :class="(props.row.is_active == 1 || props.row.is_active === true) ? 'badge-success' : 'badge-secondary'">{{ (props.row.is_active == 1 || props.row.is_active === true) ? $t('Yes') : $t('No') }}</span>
          </span>
          <span v-else-if="props.column.field == 'actions'">
            <a @click="openLedger(props.row)" :title="$t('Account_Ledger') || 'Ledger'" class="mr-2 cursor-pointer" v-b-tooltip.hover>
              <lucide-icon class="text-25 text-info" name="book-open" />
            </a>
            <a @click="openEdit(props.row)" :title="$t('Edit')" class="mr-2 cursor-pointer" v-b-tooltip.hover>
              <lucide-icon class="text-25 text-success" name="pencil" />
            </a>
            <a :title="$t('Delete')" v-b-tooltip.hover class="cursor-pointer" @click="confirmRemove(props.row)">
              <lucide-icon class="text-25 text-danger" name="x" />
            </a>
          </span>
        </template>
      </vue-good-table>
    </div>

    <!-- Create / Edit Modal -->
    <div class="modal fade" tabindex="-1" role="dialog" :class="{ show: showModal }" :style="{ display: showModal ? 'block' : 'none' }">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ editing ? $t('Edit_Account') : $t('New_Account') }}</h5>
            <button type="button" class="close" @click="closeModal"><span>&times;</span></button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>{{ $t('Code') }}</label>
              <input v-model.trim="form.code" class="form-control" :placeholder="$t('Example_Code')" />
            </div>
            <div class="form-group">
              <label>{{ $t('Name') }}</label>
              <input v-model.trim="form.name" class="form-control" :placeholder="$t('Example_Name')" />
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>{{ $t('Type') }}</label>
                <select v-model="form.type" class="form-control">
                  <option disabled value="">{{ $t('Select_Type') }}</option>
                  <option value="asset">{{ $t('Asset') }}</option>
                  <option value="liability">{{ $t('Liability') }}</option>
                  <option value="equity">{{ $t('Equity') }}</option>
                  <option value="income">{{ $t('Income') }}</option>
                  <option value="expense">{{ $t('Expense') }}</option>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label>{{ $t('Parent') }}</label>
                <select v-model="form.parent_id" class="form-control">
                  <option :value="null">{{ $t('None') }}</option>
                  <option v-for="p in rows" :key="p.id" :value="p.id">{{ p.code }} — {{ p.name }}</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>{{ $t('Status') }}</label>
              <select v-model.number="form.is_active" class="form-control">
                <option :value="1">{{ $t('Active') }}</option>
                <option :value="0">{{ $t('Inactive') }}</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" @click="closeModal" :disabled="btnLoading">{{ $t('Cancel') }}</button>
            <button type="button" class="btn btn-primary" @click="save" :disabled="btnLoading || !canSave">
              <span v-if="btnLoading" class="spinner-border spinner-border-sm mr-2"></span>
              <span>{{ btnLoading ? $t('Saving') : $t('Save') }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ACCOUNT LEDGER MODAL -->
    <div class="modal fade" tabindex="-1" role="dialog" :class="{ show: showLedgerModal }" :style="{ display: showLedgerModal ? 'block' : 'none' }">
      <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
          <div class="modal-header bg-light">
            <div>
              <h5 class="modal-title font-weight-bold mb-0">
                <lucide-icon name="book-open" class="mr-1 text-primary" />
                {{ $t('Account_Ledger') || 'General Ledger' }}: {{ selectedAccount ? selectedAccount.name : '' }}
              </h5>
              <small class="text-muted">
                {{ $t('Code') }}: <strong>{{ selectedAccount ? selectedAccount.code : '' }}</strong> | 
                {{ $t('Type') }}: <span class="badge badge-info">{{ (selectedAccount ? selectedAccount.type : '').toUpperCase() }}</span>
              </small>
            </div>
            <button type="button" class="close" @click="closeLedger"><span>&times;</span></button>
          </div>
          <div class="modal-body p-3">
            <!-- Filter Bar -->
            <div class="card p-3 mb-3 bg-light border-0">
              <div class="row align-items-end">
                <div class="col-md-3 mb-2">
                  <label class="small text-muted mb-1">{{ $t('From') || 'From Date' }}</label>
                  <input v-model="ledgerFilter.from" type="date" class="form-control form-control-sm" />
                </div>
                <div class="col-md-3 mb-2">
                  <label class="small text-muted mb-1">{{ $t('To') || 'To Date' }}</label>
                  <input v-model="ledgerFilter.to" type="date" class="form-control form-control-sm" />
                </div>
                <div class="col-md-3 mb-2">
                  <label class="small text-muted mb-1">{{ $t('Search') || 'Search' }}</label>
                  <input v-model="ledgerFilter.search" type="text" class="form-control form-control-sm" placeholder="Ref, memo, desc..." />
                </div>
                <div class="col-md-3 mb-2 text-right">
                  <button class="btn btn-sm btn-primary mr-1" @click="fetchLedger(selectedAccount ? selectedAccount.id : null)">
                    <lucide-icon name="filter" /> {{ $t('Filter') }}
                  </button>
                  <button class="btn btn-sm btn-outline-secondary mr-1" @click="resetLedgerFilter">
                    {{ $t('Reset') || 'Reset' }}
                  </button>
                  <button class="btn btn-sm btn-outline-dark" @click="printLedger">
                    <lucide-icon name="printer" /> {{ $t('Print') || 'Print' }}
                  </button>
                </div>
              </div>
            </div>

            <!-- KPI Cards -->
            <div class="row mb-3">
              <div class="col-md-3">
                <div class="card p-2 border-left-primary shadow-sm">
                  <div class="small text-muted">{{ $t('Opening_Balance') || 'Opening Balance' }}</div>
                  <div class="h5 mb-0 font-weight-bold">{{ toMoney(ledgerData.opening_balance) }}</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card p-2 border-left-success shadow-sm">
                  <div class="small text-muted">{{ $t('Total_Debit') || 'Total Debit' }}</div>
                  <div class="h5 mb-0 text-success font-weight-bold">{{ toMoney(ledgerData.total_debit) }}</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card p-2 border-left-danger shadow-sm">
                  <div class="small text-muted">{{ $t('Total_Credit') || 'Total Credit' }}</div>
                  <div class="h5 mb-0 text-danger font-weight-bold">{{ toMoney(ledgerData.total_credit) }}</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card p-2 border-left-info shadow-sm">
                  <div class="small text-muted">{{ $t('Closing_Balance') || 'Closing Balance' }}</div>
                  <div class="h5 mb-0 text-info font-weight-bold">{{ toMoney(ledgerData.closing_balance) }}</div>
                </div>
              </div>
            </div>

            <!-- Transactions Table -->
            <div id="ledger-print-area" class="table-responsive" style="max-height: 480px; overflow-y: auto;">
              <table class="table table-bordered table-striped table-sm mb-0">
                <thead class="thead-dark text-center">
                  <tr>
                    <th style="width: 100px;">{{ $t('Date') }}</th>
                    <th style="width: 130px;">{{ $t('Reference') }}</th>
                    <th style="width: 120px;">{{ $t('Type') }}</th>
                    <th>{{ $t('Description') }} / {{ $t('Memo') }}</th>
                    <th class="text-right" style="width: 120px;">{{ $t('Debit') }}</th>
                    <th class="text-right" style="width: 120px;">{{ $t('Credit') }}</th>
                    <th class="text-right" style="width: 130px;">{{ $t('Balance') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="ledgerLoading">
                    <td colspan="7" class="text-center py-4">
                      <div class="spinner-border spinner-border-sm text-primary"></div>
                      <span class="ml-2">{{ $t('Loading') || 'Loading...' }}</span>
                    </td>
                  </tr>
                  <!-- Opening balance row if filtered by date -->
                  <tr v-if="!ledgerLoading && ledgerFilter.from" class="bg-light font-weight-bold">
                    <td colspan="4" class="text-right">{{ $t('Opening_Balance') || 'Opening Balance' }}:</td>
                    <td class="text-right">-</td>
                    <td class="text-right">-</td>
                    <td class="text-right">{{ toMoney(ledgerData.opening_balance) }}</td>
                  </tr>
                  <tr v-if="!ledgerLoading && (!ledgerData.data || ledgerData.data.length === 0)">
                    <td colspan="7" class="text-center text-muted py-4">
                      {{ $t('No_Data_Available') || 'No transactions found for this account.' }}
                    </td>
                  </tr>
                  <tr v-for="(tx, idx) in (ledgerData.data || [])" :key="idx">
                    <td class="text-center">{{ tx.date }}</td>
                    <td class="font-weight-bold text-center">
                      <span class="badge badge-light border">{{ tx.reference_no || ('#' + tx.reference_id) }}</span>
                    </td>
                    <td class="text-center text-capitalize">{{ formatRefType(tx.reference_type) }}</td>
                    <td>
                      <div>{{ tx.description || '-' }}</div>
                      <small class="text-muted" v-if="tx.memo && tx.memo !== tx.description"><em>{{ tx.memo }}</em></small>
                    </td>
                    <td class="text-right text-success font-weight-bold">
                      {{ tx.debit > 0 ? toMoney(tx.debit) : '-' }}
                    </td>
                    <td class="text-right text-danger font-weight-bold">
                      {{ tx.credit > 0 ? toMoney(tx.credit) : '-' }}
                    </td>
                    <td class="text-right font-weight-bold" :class="Number(tx.balance) < 0 ? 'text-danger' : 'text-dark'">
                      {{ toMoney(tx.balance) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-light font-weight-bold" v-if="!ledgerLoading && ledgerData.data && ledgerData.data.length > 0">
                  <tr>
                    <td colspan="4" class="text-right">{{ $t('Total') || 'Totals' }}:</td>
                    <td class="text-right text-success">{{ toMoney(ledgerData.total_debit) }}</td>
                    <td class="text-right text-danger">{{ toMoney(ledgerData.total_credit) }}</td>
                    <td class="text-right text-info">{{ toMoney(ledgerData.closing_balance) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeLedger">{{ $t('Close') }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  name: "ChartOfAccountsV2",
  data() {
    return {
      isLoading: true,
      rows: [],
      totalRows: "",
      serverParams: {
        columnFilters: {},
        sort: { field: "code", type: "asc" },
        page: 1,
        perPage: 50
      },
      search: "",
      limit: "50",
      filterType: "",
      showModal: false,
      editing: false,
      form: { id: null, code: "", name: "", type: "", parent_id: null, is_active: 1 },
      btnLoading: false,

      // Ledger Modal State
      showLedgerModal: false,
      selectedAccount: null,
      ledgerLoading: false,
      ledgerFilter: {
        from: "",
        to: "",
        search: ""
      },
      ledgerData: {
        opening_balance: 0,
        closing_balance: 0,
        total_debit: 0,
        total_credit: 0,
        data: []
      },
      price_format_key: null
    };
  },
  computed: {
    columns() {
      return [
        { label: this.$t('Code'), field: 'code', tdClass: 'text-left font-weight-bold', thClass: 'text-left' },
        { label: this.$t('Name'), field: 'name', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Parent'), field: 'parent', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Type'), field: 'type', tdClass: 'text-center', thClass: 'text-center' },
        { label: this.$t('Balance') || 'Balance', field: 'balance', sortable: false, tdClass: 'text-right', thClass: 'text-right' },
        { label: this.$t('Active'), field: 'active', sortable: false, tdClass: 'text-center', thClass: 'text-center' },
        { label: this.$t('Action'), field: 'actions', sortable: false, tdClass: 'text-left', thClass: 'text-left' },
      ];
    },
    canSave() { return this.form.code && this.form.name && this.form.type; }
  },
  created() {
    this.Get_Coa(1);
  },
  methods: {
    updateParams(newProps) { this.serverParams = Object.assign({}, this.serverParams, newProps); },
    onPageChange({ currentPage }) { if (this.serverParams.page !== currentPage) { this.updateParams({ page: currentPage }); this.Get_Coa(currentPage); } },
    onPerPageChange({ currentPerPage }) { if (this.limit !== currentPerPage) { this.limit = currentPerPage; this.updateParams({ page: 1, perPage: currentPerPage }); this.Get_Coa(1); } },
    onSortChange(params) {
      if (!params || !params.length) return;
      this.updateParams({ sort: { type: params[0].type, field: params[0].field } });
      this.Get_Coa(this.serverParams.page);
    },
    onSearch(value) { this.search = value.searchTerm; this.Get_Coa(this.serverParams.page); },
    onTypeChange() {
      this.updateParams({ page: 1 });
      this.Get_Coa(1);
    },
    async Get_Coa(page) {
      NProgress.start(); NProgress.set(0.1);
      axios.get(
        "/accounting/v2/coa?page=" + page +
        "&SortField=" + this.serverParams.sort.field +
        "&SortType=" + this.serverParams.sort.type +
        "&search=" + encodeURIComponent(this.search || '') +
        "&type=" + encodeURIComponent(this.filterType || '') +
        "&limit=" + this.limit
      )
      .then(({data}) => {
        this.rows = data && data.data ? data.data : [];
        this.totalRows = data && (data.totalRows ?? 0);
        NProgress.done();
        this.isLoading = false;
      })
      .catch(() => { NProgress.done(); this.isLoading = false; });
    },
    parentName(id) {
      if (!id) return this.$t('None');
      const p = this.rows.find(x => x.id === id);
      return p ? (p.code + ' — ' + p.name) : '-';
    },
    typeBadgeClass(type) {
      switch ((type || '').toLowerCase()) {
        case 'asset': return 'badge-success';
        case 'liability': return 'badge-danger';
        case 'equity': return 'badge-info';
        case 'income': return 'badge-primary';
        case 'expense': return 'badge-warning';
        default: return 'badge-secondary';
      }
    },
    openCreate() {
      this.editing = false;
      this.form = { id: null, code: "", name: "", type: "", parent_id: null, is_active: 1 };
      this.btnLoading = false;
      this.showModal = true;
    },
    openEdit(row) {
      this.editing = true;
      const activeVal = (row.is_active == 1 || row.is_active === true || row.is_active === '1') ? 1 : 0;
      this.form = { id: row.id, code: row.code, name: row.name, type: row.type, parent_id: row.parent_id, is_active: activeVal };
      this.btnLoading = false;
      this.showModal = true;
    },
    closeModal() { this.btnLoading = false; this.showModal = false; },
    async save() {
      try {
        this.btnLoading = true;
        if (this.editing) {
          await axios.put(`/accounting/v2/coa/${this.form.id}`, this.form);
          this.makeToast('success', this.$t('Account_Updated'), this.$t('Success'));
        } else {
          await axios.post(`/accounting/v2/coa`, this.form);
          this.makeToast('success', this.$t('Account_Created'), this.$t('Success'));
        }
        this.showModal = false;
        this.Get_Coa(this.serverParams.page);
      } catch (e) { this.makeToast('danger', this.$t('Operation_Failed'), this.$t('Error')); }
      finally { this.btnLoading = false; }
    },
    confirmRemove(row) {
      this.$swal({ title: this.$t('Delete'), text: this.$t('Delete_Account_Warning'), type: 'warning', showCancelButton: true, confirmButtonText: this.$t('Delete') }).then(result => { if (result.value) this.removeRow(row); });
    },
    async removeRow(row) {
      try { await axios.delete(`/accounting/v2/coa/${row.id}`); this.makeToast('success', this.$t('Deleted_Successfully'), this.$t('Success')); this.Get_Coa(this.serverParams.page); }
      catch (e) { this.makeToast('danger', this.$t('Delete_Failed'), this.$t('Error')); }
    },

    // ----------------------------------------------------
    // ACCOUNT LEDGER METHODS
    // ----------------------------------------------------
    onRowDblClick(params) {
      if (params && params.row) {
        this.openLedger(params.row);
      }
    },
    openLedger(account) {
      if (!account || !account.id) return;
      this.$router.push({
        name: 'accounting_v2_account_ledger',
        params: { id: account.id }
      });
    },
    closeLedger() {
      this.showLedgerModal = false;
      this.selectedAccount = null;
    },
    resetLedgerFilter() {
      this.ledgerFilter = { from: "", to: "", search: "" };
      if (this.selectedAccount) {
        this.fetchLedger(this.selectedAccount.id);
      }
    },
    async fetchLedger(accountId) {
      if (!accountId) return;
      this.ledgerLoading = true;
      try {
        const { data } = await axios.get(`/accounting/v2/coa/${accountId}/ledger`, {
          params: {
            from: this.ledgerFilter.from || undefined,
            to: this.ledgerFilter.to || undefined,
            search: this.ledgerFilter.search || undefined
          }
        });
        this.ledgerData = data || { opening_balance: 0, closing_balance: 0, total_debit: 0, total_credit: 0, data: [] };
      } catch (e) {
        this.makeToast('danger', 'Failed to load account ledger', this.$t('Error'));
      } finally {
        this.ledgerLoading = false;
      }
    },
    formatRefType(type) {
      if (!type) return '-';
      return String(type).replace(/_/g, ' ');
    },
    printLedger() {
      window.print();
    },
    toMoney(v) {
      try {
        const n = parseFloat(v || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) this.price_format_key = key;
        return formatPriceDisplayHelper(n, 2, key || null);
      } catch (e) {
        const n = parseFloat(v || 0);
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, { title, variant, solid: true });
    }
  }
};
</script>

<style scoped>
.sortable { cursor: pointer; }
.cursor-pointer { cursor: pointer; }
.hover-underline:hover { text-decoration: underline !important; }
.vgt-table tr:hover { background-color: #f8faff !important; }
.modal { background: rgba(0,0,0,.5); }
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-danger { border-left: 4px solid #e74a3b !important; }
.border-left-info { border-left: 4px solid #36b9cc !important; }

@media print {
  body * { visibility: hidden; }
  #ledger-print-area, #ledger-print-area * { visibility: visible; }
  #ledger-print-area { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>

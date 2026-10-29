<template>
  <div class="main-content">
    <!-- Breadcrumb & Header -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
      <div>
        <div class="d-flex align-items-center mb-1">
          <router-link to="/app/accounting-v2/chart-of-accounts" class="text-muted text-decoration-none small mr-2">
            <lucide-icon name="arrow-left" style="width: 14px; height: 14px; vertical-align: -2px;" />
            {{ $t('Chart_of_Accounts_Title') || 'Chart of Accounts' }}
          </router-link>
          <span class="text-muted small mr-2">/</span>
          <span class="small font-weight-bold text-dark">{{ $t('Account_Ledger') || 'Account Ledger' }}</span>
        </div>
        <h3 class="mb-0 font-weight-bold text-dark d-flex align-items-center flex-wrap">
          <span v-if="account">{{ account.code }} — {{ account.name }}</span>
          <span v-else>{{ $t('Account_Ledger') || 'General Ledger' }}</span>
          <span v-if="account && account.type" class="badge ml-2 text-uppercase" :class="typeBadgeClass(account.type)" style="font-size: 11px;">
            {{ account.type }}
          </span>
        </h3>
      </div>
      <div class="mt-2 mt-md-0 d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm mr-2" @click="$router.push('/app/accounting-v2/chart-of-accounts')">
          <lucide-icon name="arrow-left" style="width: 14px; height: 14px;" /> {{ $t('Back') || 'Back' }}
        </button>
        <button class="btn btn-outline-dark btn-sm" @click="printLedger">
          <lucide-icon name="printer" style="width: 14px; height: 14px;" /> {{ $t('Print') || 'Print Ledger' }}
        </button>
      </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="row mb-3">
      <div class="col-sm-6 col-lg-3 mb-2">
        <div class="card p-3 shadow-sm border-0 border-left-info h-100">
          <div class="text-muted small text-uppercase font-weight-600">{{ $t('Opening_Balance') || 'Opening Balance' }}</div>
          <div class="h4 mb-0 text-info font-weight-bold mt-1">
            {{ toMoney(ledgerData.opening_balance) }}
          </div>
          <small class="text-muted mt-1">{{ filter.from ? 'As of ' + filter.from : 'Initial' }}</small>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3 mb-2">
        <div class="card p-3 shadow-sm border-0 border-left-success h-100">
          <div class="text-muted small text-uppercase font-weight-600">{{ $t('Total_Debit') || 'Total Debit (Inflow)' }}</div>
          <div class="h4 mb-0 text-success font-weight-bold mt-1">
            {{ toMoney(ledgerData.total_debit) }}
          </div>
          <small class="text-muted mt-1">{{ (ledgerData.data || []).filter(x => Number(x.debit) > 0).length }} Debits</small>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3 mb-2">
        <div class="card p-3 shadow-sm border-0 border-left-danger h-100">
          <div class="text-muted small text-uppercase font-weight-600">{{ $t('Total_Credit') || 'Total Credit (Outflow)' }}</div>
          <div class="h4 mb-0 text-danger font-weight-bold mt-1">
            {{ toMoney(ledgerData.total_credit) }}
          </div>
          <small class="text-muted mt-1">{{ (ledgerData.data || []).filter(x => Number(x.credit) > 0).length }} Credits</small>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3 mb-2">
        <div class="card p-3 shadow-sm border-0 border-left-primary h-100">
          <div class="text-muted small text-uppercase font-weight-600">{{ $t('Closing_Balance') || 'Current Balance' }}</div>
          <div class="h4 mb-0 font-weight-bold mt-1" :class="Number(ledgerData.closing_balance) < 0 ? 'text-danger' : 'text-primary'">
            {{ toMoney(ledgerData.closing_balance) }}
          </div>
          <small class="text-muted mt-1">{{ (ledgerData.data || []).length }} Transactions</small>
        </div>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="card p-3 mb-3 shadow-sm border-0">
      <div class="row align-items-end">
        <div class="col-md-3 col-sm-6 mb-2">
          <label class="small text-muted mb-1">{{ $t('From_Date') || 'From Date' }}</label>
          <input v-model="filter.from" type="date" class="form-control form-control-sm" />
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <label class="small text-muted mb-1">{{ $t('To_Date') || 'To Date' }}</label>
          <input v-model="filter.to" type="date" class="form-control form-control-sm" />
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <label class="small text-muted mb-1">{{ $t('Search') || 'Search Memo / Ref' }}</label>
          <input
            v-model="filter.search"
            type="text"
            class="form-control form-control-sm"
            :placeholder="$t('Search') + '...'"
            @keyup.enter="applyFilter"
          />
        </div>
        <div class="col-md-3 col-sm-6 mb-2 d-flex gap-2">
          <button class="btn btn-primary btn-sm mr-2 flex-grow-1" @click="applyFilter" :disabled="loading">
            <span v-if="loading" class="spinner-border spinner-border-sm mr-1"></span>
            <lucide-icon v-else name="filter" style="width: 14px; height: 14px;" /> {{ $t('Filter') || 'Filter' }}
          </button>
          <button class="btn btn-outline-secondary btn-sm" @click="resetFilter" :disabled="loading">
            <lucide-icon name="rotate-ccw" style="width: 14px; height: 14px;" /> {{ $t('Reset') || 'Reset' }}
          </button>
        </div>
      </div>

      <!-- Quick Preset Dates -->
      <div class="d-flex align-items-center mt-2 flex-wrap pt-2 border-top">
        <span class="small text-muted mr-2">{{ $t('Quick_Ranges') || 'Quick:' }}</span>
        <button type="button" class="btn btn-outline-light btn-xs mr-1 text-dark border" @click="setPresetRange('today')">Today</button>
        <button type="button" class="btn btn-outline-light btn-xs mr-1 text-dark border" @click="setPresetRange('this_month')">This Month</button>
        <button type="button" class="btn btn-outline-light btn-xs mr-1 text-dark border" @click="setPresetRange('this_year')">This Year</button>
        <button type="button" class="btn btn-outline-light btn-xs text-dark border" @click="setPresetRange('all')">All Time</button>
      </div>
    </div>

    <!-- Ledger Table Card / Printable Area -->
    <div class="card shadow-sm border-0" id="ledger-print-area">
      <!-- Printable Header (shown only when printing) -->
      <div class="d-none d-print-block p-4 border-bottom">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="font-weight-bold mb-1">{{ account ? account.code + ' — ' + account.name : 'Account Ledger' }}</h2>
            <div class="text-muted">Account Type: {{ (account && account.type ? account.type : '').toUpperCase() }}</div>
          </div>
          <div class="text-right">
            <div><strong>Date Range:</strong> {{ filter.from || 'Beginning' }} to {{ filter.to || 'Present' }}</div>
            <div><strong>Closing Balance:</strong> {{ toMoney(ledgerData.closing_balance) }}</div>
          </div>
        </div>
      </div>

      <div class="card-body p-0">
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
          </div>
          <div class="text-muted mt-2 small">Loading transaction history...</div>
        </div>

        <div v-else class="table-responsive">
          <table class="table table-hover table-striped mb-0">
            <thead class="thead-light">
              <tr>
                <th style="width: 110px;">{{ $t('Date') || 'Date' }}</th>
                <th style="width: 140px;">{{ $t('Type') || 'Type' }}</th>
                <th style="width: 140px;">{{ $t('Reference') || 'Ref No.' }}</th>
                <th>{{ $t('Description') || 'Description / Memo' }}</th>
                <th class="text-right" style="width: 130px;">{{ $t('Debit') || 'Debit' }}</th>
                <th class="text-right" style="width: 130px;">{{ $t('Credit') || 'Credit' }}</th>
                <th class="text-right" style="width: 150px;">{{ $t('Balance') || 'Running Balance' }}</th>
              </tr>
            </thead>
            <tbody>
              <!-- Opening Balance Row -->
              <tr class="table-info font-weight-bold">
                <td>{{ filter.from || '-' }}</td>
                <td><span class="badge badge-light border">OPENING</span></td>
                <td>-</td>
                <td>
                  <em>{{ $t('Opening_Balance') || 'Opening Balance Brought Forward' }}</em>
                </td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right text-info">
                  {{ toMoney(ledgerData.opening_balance) }}
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="!ledgerData.data || ledgerData.data.length === 0">
                <td colspan="7" class="text-center py-4 text-muted">
                  <lucide-icon name="inbox" style="width: 32px; height: 32px;" class="mb-2 text-muted" />
                  <div>No transactions recorded in this period.</div>
                </td>
              </tr>

              <!-- Transactions Rows (Paginated) -->
              <tr v-for="tx in paginatedTransactions" :key="tx.line_id">
                <td class="font-weight-600">{{ tx.date }}</td>
                <td>
                  <span class="badge" :class="refBadgeClass(tx.reference_type)">
                    {{ formatRefType(tx.reference_type) }}
                  </span>
                </td>
                <td>
                  <a
                    href="javascript:void(0)"
                    @click="goToSourceDetail(tx)"
                    class="font-weight-600 text-primary text-decoration-none d-inline-flex align-items-center"
                    :title="'Click to view ' + (tx.reference_type || 'details')"
                  >
                    <span>{{ tx.reference_no || ('#' + tx.journal_entry_id) }}</span>
                    <lucide-icon name="external-link" style="width: 12px; height: 12px; margin-left: 4px;" />
                  </a>
                </td>
                <td>
                  <div>{{ tx.description || '-' }}</div>
                  <small v-if="tx.memo && tx.memo !== tx.description" class="text-muted d-block">
                    Memo: {{ tx.memo }}
                  </small>
                </td>
                <td class="text-right font-weight-bold" :class="Number(tx.debit) > 0 ? 'text-success' : 'text-muted'">
                  {{ Number(tx.debit) > 0 ? toMoney(tx.debit) : '-' }}
                </td>
                <td class="text-right font-weight-bold" :class="Number(tx.credit) > 0 ? 'text-danger' : 'text-muted'">
                  {{ Number(tx.credit) > 0 ? toMoney(tx.credit) : '-' }}
                </td>
                <td class="text-right font-weight-bold" :class="Number(tx.balance) < 0 ? 'text-danger' : 'text-dark'">
                  {{ toMoney(tx.balance) }}
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-light font-weight-bold" v-if="ledgerData.data && ledgerData.data.length > 0">
              <tr>
                <td colspan="4" class="text-right">{{ $t('Total') || 'Totals' }}:</td>
                <td class="text-right text-success">{{ toMoney(ledgerData.total_debit) }}</td>
                <td class="text-right text-danger">{{ toMoney(ledgerData.total_credit) }}</td>
                <td class="text-right text-primary">{{ toMoney(ledgerData.closing_balance) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div class="d-flex align-items-center justify-content-between p-3 border-top flex-wrap" v-if="ledgerData.data && ledgerData.data.length > 0">
          <div class="d-flex align-items-center mb-2 mb-md-0">
            <span class="small text-muted mr-2">Rows:</span>
            <select v-model.number="perPage" class="form-control form-control-sm mr-3" style="width: 75px;" @change="currentPage = 1">
              <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
            <span class="small text-muted">
              Showing {{ paginationStart }} to {{ paginationEnd }} of {{ (ledgerData.data || []).length }} entries
            </span>
          </div>

          <div class="d-flex align-items-center">
            <button
              class="btn btn-outline-secondary btn-sm mr-1"
              :disabled="currentPage <= 1"
              @click="currentPage = 1"
              title="First Page"
            >
              &laquo;
            </button>
            <button
              class="btn btn-outline-secondary btn-sm mr-2"
              :disabled="currentPage <= 1"
              @click="currentPage--"
              title="Previous Page"
            >
              {{ $t('Prev') || 'Prev' }}
            </button>
            <span class="small font-weight-bold mx-2">
              Page {{ currentPage }} of {{ totalPages }}
            </span>
            <button
              class="btn btn-outline-secondary btn-sm mr-1"
              :disabled="currentPage >= totalPages"
              @click="currentPage++"
              title="Next Page"
            >
              {{ $t('Next') || 'Next' }}
            </button>
            <button
              class="btn btn-outline-secondary btn-sm"
              :disabled="currentPage >= totalPages"
              @click="currentPage = totalPages"
              title="Last Page"
            >
              &raquo;
            </button>
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
  name: "AccountLedgerPage",
  data() {
    return {
      loading: true,
      accountId: null,
      account: null,
      filter: {
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
      price_format_key: null,
      currentPage: 1,
      perPage: 25,
      perPageOptions: [10, 25, 50, 100, 250],
      isPrinting: false
    };
  },
  computed: {
    totalPages() {
      const total = (this.ledgerData.data || []).length;
      return Math.ceil(total / this.perPage) || 1;
    },
    paginatedTransactions() {
      if (this.isPrinting) return this.ledgerData.data || [];
      const list = this.ledgerData.data || [];
      const start = (this.currentPage - 1) * this.perPage;
      return list.slice(start, start + this.perPage);
    },
    paginationStart() {
      if (!this.ledgerData.data || this.ledgerData.data.length === 0) return 0;
      return (this.currentPage - 1) * this.perPage + 1;
    },
    paginationEnd() {
      const list = this.ledgerData.data || [];
      const end = this.currentPage * this.perPage;
      return end > list.length ? list.length : end;
    }
  },
  created() {
    this.accountId = this.$route.params.id;
    if (this.accountId) {
      this.fetchLedger();
    } else {
      this.$router.push('/app/accounting-v2/chart-of-accounts');
    }
  },
  methods: {
    async fetchLedger() {
      if (!this.accountId) return;
      this.loading = true;
      NProgress.start();
      NProgress.set(0.1);

      try {
        const { data } = await axios.get(`/accounting/v2/coa/${this.accountId}/ledger`, {
          params: {
            from: this.filter.from || undefined,
            to: this.filter.to || undefined,
            search: this.filter.search || undefined
          }
        });

        if (data) {
          this.account = data.account || null;
          this.ledgerData = {
            opening_balance: data.opening_balance || 0,
            closing_balance: data.closing_balance || 0,
            total_debit: data.total_debit || 0,
            total_credit: data.total_credit || 0,
            data: data.data || []
          };
        }
      } catch (e) {
        this.$root.$bvToast.toast("Failed to load account ledger", {
          title: "Error",
          variant: "danger",
          solid: true
        });
      } finally {
        this.loading = false;
        NProgress.done();
      }
    },
    applyFilter() {
      this.currentPage = 1;
      this.fetchLedger();
    },
    resetFilter() {
      this.filter = { from: "", to: "", search: "" };
      this.currentPage = 1;
      this.fetchLedger();
    },
    setPresetRange(preset) {
      const today = new Date();
      const formatDate = (d) => d.toISOString().split('T')[0];

      if (preset === 'today') {
        const dStr = formatDate(today);
        this.filter.from = dStr;
        this.filter.to = dStr;
      } else if (preset === 'this_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        this.filter.from = formatDate(firstDay);
        this.filter.to = formatDate(lastDay);
      } else if (preset === 'this_year') {
        const firstDay = new Date(today.getFullYear(), 0, 1);
        const lastDay = new Date(today.getFullYear(), 11, 31);
        this.filter.from = formatDate(firstDay);
        this.filter.to = formatDate(lastDay);
      } else if (preset === 'all') {
        this.filter.from = '';
        this.filter.to = '';
      }
      this.fetchLedger();
    },
    typeBadgeClass(type) {
      const map = {
        asset: 'badge-success',
        liability: 'badge-danger',
        equity: 'badge-info',
        income: 'badge-primary',
        expense: 'badge-warning',
      };
      return map[type] || 'badge-secondary';
    },
    refBadgeClass(type) {
      const t = String(type || '').toLowerCase();
      if (t.includes('sale') || t.includes('revenue')) return 'badge-success';
      if (t.includes('purchase') || t.includes('expense')) return 'badge-warning';
      if (t.includes('return')) return 'badge-danger';
      if (t.includes('deposit') || t.includes('transfer')) return 'badge-info';
      return 'badge-secondary';
    },
    formatRefType(type) {
      if (!type) return 'Entry';
      return String(type).replace(/_/g, ' ').toUpperCase();
    },
    goToSourceDetail(tx) {
      if (!tx) return;
      const type = String(tx.reference_type || '').toLowerCase();
      const id = tx.source_id || tx.reference_id;

      if (type.includes('sale') && !type.includes('return')) {
        if (id) {
          this.$router.push({ name: 'detail_sale', params: { id: id } });
        } else {
          this.$router.push('/app/sales/list');
        }
      } else if (type.includes('purchase') && !type.includes('return')) {
        if (id) {
          this.$router.push({ name: 'detail_purchase', params: { id: id } });
        } else {
          this.$router.push('/app/purchases/list');
        }
      } else if (type.includes('expense')) {
        if (id) {
          this.$router.push({ name: 'detail_expense', params: { id: id } });
        } else {
          this.$router.push('/app/expenses/list');
        }
      } else if (type.includes('return')) {
        if (id && type.includes('sale')) {
          this.$router.push({ name: 'sale_return', params: { id: id } });
        } else {
          this.$router.push('/app/sales/list');
        }
      } else if (type.includes('transfer')) {
        this.$router.push('/app/accounts/transfer_money');
      } else {
        this.$router.push('/app/accounting-v2/journal-entries');
      }
    },
    printLedger() {
      this.isPrinting = true;
      this.$nextTick(() => {
        window.print();
        setTimeout(() => {
          this.isPrinting = false;
        }, 500);
      });
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
    }
  }
};
</script>

<style scoped>
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #10b981 !important; }
.border-left-danger { border-left: 4px solid #ef4444 !important; }
.border-left-info { border-left: 4px solid #3b82f6 !important; }
.btn-xs { padding: 2px 8px; font-size: 11px; border-radius: 4px; }

@media print {
  body * { visibility: hidden; }
  #ledger-print-area, #ledger-print-area * { visibility: visible; }
  #ledger-print-area { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>

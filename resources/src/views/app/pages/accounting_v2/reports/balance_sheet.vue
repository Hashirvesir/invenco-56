<template>
  <!-- NEW FEATURE - SAFE ADDITION -->
  <div class="main-content">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-1 font-weight-bold">{{ $t('Balance_Sheet_Title') || 'Balance Sheet' }}</h4>
        <div class="text-muted small">{{ $t('Balance_Sheet_Subtitle') || 'Financial Statement of Assets, Liabilities & Equity' }}</div>
      </div>
      <div>
        <button class="btn btn-outline-dark btn-sm" @click="printReport">
          <lucide-icon name="printer" /> {{ $t('Print') || 'Print Report' }}
        </button>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="card p-3 mb-3 shadow-sm border-0">
      <div class="row align-items-end">
        <div class="col-md-4 mb-2">
          <label class="small text-muted mb-1">{{ $t('As_Of') || 'As of Date' }}</label>
          <input v-model="to" type="date" class="form-control" />
        </div>
        <div class="col-md-4 mb-2">
          <button class="btn btn-primary" @click="fetch" :disabled="loading">
            <span v-if="loading" class="spinner-border spinner-border-sm mr-1"></span>
            <lucide-icon v-else name="filter" /> {{ $t('Filter') }}
          </button>
        </div>
      </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="row mb-3">
      <div class="col-md-4">
        <div class="card p-3 shadow-sm border-left-success">
          <div class="text-muted small">{{ $t('Total_Assets') || 'Total Assets' }}</div>
          <div class="h4 mb-0 text-success font-weight-bold">{{ toMoney(data.total_assets || data.assets) }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-3 shadow-sm border-left-danger">
          <div class="text-muted small">{{ $t('Total_Liabilities') || 'Total Liabilities' }}</div>
          <div class="h4 mb-0 text-danger font-weight-bold">{{ toMoney(data.liabilities) }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-3 shadow-sm border-left-info">
          <div class="text-muted small">{{ $t('Total_Equity') || 'Total Equity' }}</div>
          <div class="h4 mb-0 text-info font-weight-bold">{{ toMoney(data.equity) }}</div>
        </div>
      </div>
    </div>

    <!-- Balance Check Banner -->
    <div class="alert p-3 mb-4 d-flex justify-content-between align-items-center shadow-sm"
         :class="Math.abs(data.balance) < 0.01 ? 'alert-success border-success' : 'alert-warning border-warning'">
      <div>
        <strong>{{ $t('Balance_Check') || 'Balance Status' }}:</strong>
        <span v-if="Math.abs(data.balance) < 0.01" class="ml-2 font-weight-bold">
          ✓ Balanced (Assets = Liabilities + Equity)
        </span>
        <span v-else class="ml-2 text-danger font-weight-bold">
          ⚠ Difference: {{ toMoney(data.balance) }}
        </span>
      </div>
      <div class="small text-muted">
        As of: <strong>{{ data.date || to || 'Today' }}</strong>
      </div>
    </div>

    <!-- Detailed Financial Statement (Printable) -->
    <div id="balance-sheet-report" class="card shadow-sm border-0 p-4">
      <div class="text-center mb-4 pb-3 border-bottom">
        <h3 class="font-weight-bold mb-1">{{ $t('Balance_Sheet_Title') || 'BALANCE SHEET' }}</h3>
        <div class="text-muted font-italic">{{ $t('As_Of') || 'As of' }}: {{ data.date || to }}</div>
      </div>

      <div class="row">
        <!-- LEFT COLUMN: ASSETS -->
        <div class="col-md-6 mb-4">
          <div class="border rounded p-3 h-100">
            <h5 class="font-weight-bold text-success border-bottom pb-2 mb-3">
              {{ $t('Assets') || 'ASSETS' }}
            </h5>

            <table class="table table-sm table-borderless table-hover">
              <tbody>
                <tr v-for="item in (data.assets_breakdown || [])" :key="item.id" @click="$router.push({ name: 'accounting_v2_account_ledger', params: { id: item.id } })" class="cursor-pointer">
                  <td>
                    <span class="badge badge-light border mr-1">{{ item.code }}</span>
                    <span class="text-primary font-weight-600 hover-underline">{{ item.name }}</span>
                  </td>
                  <td class="text-right font-weight-bold">{{ toMoney(item.amount) }}</td>
                </tr>
                <tr v-if="!data.assets_breakdown || data.assets_breakdown.length === 0">
                  <td colspan="2" class="text-muted text-center py-3">No asset entries recorded.</td>
                </tr>
              </tbody>
            </table>

            <div class="border-top pt-2 mt-auto d-flex justify-content-between font-weight-bold text-success h6 mb-0">
              <span>{{ $t('Total_Assets') || 'TOTAL ASSETS' }}:</span>
              <span>{{ toMoney(data.total_assets || data.assets) }}</span>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: LIABILITIES & EQUITY -->
        <div class="col-md-6 mb-4">
          <div class="border rounded p-3 h-100 d-flex flex-column">
            <!-- LIABILITIES SECTION -->
            <div class="mb-4">
              <h5 class="font-weight-bold text-danger border-bottom pb-2 mb-3">
                {{ $t('Liabilities') || 'LIABILITIES' }}
              </h5>

              <table class="table table-sm table-borderless table-hover">
                <tbody>
                  <tr v-for="item in (data.liabilities_breakdown || [])" :key="item.id" @click="$router.push({ name: 'accounting_v2_account_ledger', params: { id: item.id } })" class="cursor-pointer">
                    <td>
                      <span class="badge badge-light border mr-1">{{ item.code }}</span>
                      <span class="text-danger font-weight-600 hover-underline">{{ item.name }}</span>
                    </td>
                    <td class="text-right font-weight-bold">{{ toMoney(item.amount) }}</td>
                  </tr>
                  <tr v-if="!data.liabilities_breakdown || data.liabilities_breakdown.length === 0">
                    <td colspan="2" class="text-muted text-center py-2">No liability entries recorded.</td>
                  </tr>
                </tbody>
              </table>

              <div class="border-top pt-2 d-flex justify-content-between font-weight-bold text-danger">
                <span>{{ $t('Total_Liabilities') || 'TOTAL LIABILITIES' }}:</span>
                <span>{{ toMoney(data.liabilities) }}</span>
              </div>
            </div>

            <!-- EQUITY SECTION -->
            <div class="mt-2">
              <h5 class="font-weight-bold text-info border-bottom pb-2 mb-3">
                {{ $t('Equity') || 'EQUITY' }}
              </h5>

              <table class="table table-sm table-borderless table-hover">
                <tbody>
                  <tr v-for="item in (data.equity_breakdown || [])" :key="item.id" @click="$router.push({ name: 'accounting_v2_account_ledger', params: { id: item.id } })" class="cursor-pointer">
                    <td>
                      <span class="badge badge-light border mr-1">{{ item.code }}</span>
                      <span class="text-info font-weight-600 hover-underline">{{ item.name }}</span>
                    </td>
                    <td class="text-right font-weight-bold">{{ toMoney(item.amount) }}</td>
                  </tr>
                  <!-- Retained / Current Period Profit (Loss) -->
                  <tr @click="$router.push('/app/accounting-v2/reports/profit-and-loss')" class="cursor-pointer" title="Click to view Profit & Loss statement">
                    <td>
                      <span class="badge badge-light border mr-1">P&amp;L</span>
                      <span class="text-dark font-weight-600 hover-underline">{{ $t('Current_Period_Earnings') || 'Net Profit / (Loss) for Period' }}</span>
                      <lucide-icon name="external-link" style="width: 12px; height: 12px; margin-left: 4px;" class="text-muted" />
                    </td>
                    <td class="text-right font-weight-bold" :class="Number(data.net_income) < 0 ? 'text-danger' : 'text-success'">
                      {{ toMoney(data.net_income) }}
                    </td>
                  </tr>
                </tbody>
              </table>

              <div class="border-top pt-2 d-flex justify-content-between font-weight-bold text-info">
                <span>{{ $t('Total_Equity') || 'TOTAL EQUITY' }}:</span>
                <span>{{ toMoney(data.equity) }}</span>
              </div>
            </div>

            <!-- TOTAL LIABILITIES & EQUITY -->
            <div class="border-top pt-3 mt-auto d-flex justify-content-between font-weight-bold text-dark h6 mb-0">
              <span>{{ $t('Total_Liabilities_Equity') || 'TOTAL LIABILITIES & EQUITY' }}:</span>
              <span>{{ toMoney(data.total_liabilities_equity || (Number(data.liabilities || 0) + Number(data.equity || 0))) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../../utils/priceFormat";

export default {
  name: "BalanceSheetV2",
  data() {
    return {
      data: {
        assets: 0,
        liabilities: 0,
        equity: 0,
        balance: 0,
        net_income: 0,
        capital: 0,
        total_assets: 0,
        total_liabilities_equity: 0,
        assets_breakdown: [],
        liabilities_breakdown: [],
        equity_breakdown: []
      },
      to: new Date().toISOString().substr(0, 10),
      loading: false,
      price_format_key: null
    };
  },
  created() {
    this.fetch();
  },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await axios.get("/accounting/v2/reports/balance-sheet", {
          params: { to: this.to || undefined }
        });
        this.data = data || this.data;
      } catch (e) {
      } finally {
        this.loading = false;
      }
    },
    printReport() {
      window.print();
    },
    toMoney(v) {
      try {
        const n = parseFloat(v || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
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
.border-left-success { border-left: 5px solid #28a745 !important; }
.border-left-danger { border-left: 5px solid #dc3545 !important; }
.border-left-info { border-left: 5px solid #17a2b8 !important; }
.cursor-pointer { cursor: pointer; }
.hover-underline:hover { text-decoration: underline !important; }

@media print {
  body * { visibility: hidden; }
  #balance-sheet-report, #balance-sheet-report * { visibility: visible; }
  #balance-sheet-report { position: absolute; left: 0; top: 0; width: 100%; border: none !important; }
}
</style>

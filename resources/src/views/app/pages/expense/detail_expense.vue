<template>
  <div class="main-content">
    <!-- Top Action Bar -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap">
      <div>
        <div class="d-flex align-items-center mb-1">
          <router-link to="/app/expenses/list" class="text-muted text-decoration-none small mr-2">
            <lucide-icon name="arrow-left" style="width: 14px; height: 14px; vertical-align: -2px;" />
            {{ $t('Expense_List') || 'Expense List' }}
          </router-link>
          <span class="text-muted small mr-2">/</span>
          <span class="small font-weight-bold text-dark">{{ $t('Expense_Detail') || 'Expense Voucher' }}</span>
        </div>
        <h3 class="mb-0 font-weight-bold text-dark d-flex align-items-center">
          <span>{{ expense ? expense.Ref : 'Expense Voucher' }}</span>
          <span v-if="expense && expense.category_name" class="badge badge-light border ml-2 font-weight-600 text-primary" style="font-size: 13px; padding: 4px 10px;">
            {{ expense.category_name }}
          </span>
        </h3>
      </div>
      <div class="mt-2 mt-md-0 d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm mr-2" @click="$router.push('/app/expenses/list')">
          <lucide-icon name="arrow-left" style="width: 14px; height: 14px;" /> {{ $t('Back') || 'Back' }}
        </button>
        <button class="btn btn-outline-primary btn-sm mr-2" @click="printVoucher">
          <lucide-icon name="printer" style="width: 14px; height: 14px;" /> {{ $t('Print') || 'Print Voucher' }}
        </button>
        <button v-if="expense" class="btn btn-primary btn-sm" @click="$router.push('/app/expenses/edit/' + expense.id)">
          <lucide-icon name="pencil" style="width: 14px; height: 14px;" /> {{ $t('Edit') || 'Edit' }}
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-5 bg-white rounded border">
      <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
      </div>
      <div class="text-muted mt-2 small">Loading expense details...</div>
    </div>

    <!-- Neat & Clean White Voucher Card -->
    <div v-else-if="expense" class="clean-voucher-card shadow-sm" id="expense-print-area">
      <!-- Voucher Header -->
      <div class="voucher-header p-4 border-bottom bg-white">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
          <div>
            <div class="d-flex align-items-center mb-2">
              <span class="status-pill status-paid mr-2">
                <lucide-icon name="check-circle" style="width: 12px; height: 12px; vertical-align: -1px;" class="mr-1" />
                PAID VOUCHER
              </span>
              <span class="text-muted small text-uppercase font-weight-bold" style="letter-spacing: 0.5px;">
                {{ $t('Expense_Payment_Voucher') || 'Official Expense Voucher' }}
              </span>
            </div>
            <h2 class="voucher-ref font-weight-bold text-dark mb-1">{{ expense.Ref }}</h2>
            <div class="text-muted small">
              <span>Date: <strong class="text-dark">{{ expense.date }}</strong></span>
              <span v-if="expense.created_at" class="ml-3 text-muted">Created: {{ expense.created_at }}</span>
            </div>
          </div>

          <div class="amount-pill mt-3 mt-md-0">
            <span class="amount-label d-block">{{ $t('Amount_Paid') || 'Total Amount Paid' }}</span>
            <span class="amount-value">{{ toMoney(expense.amount) }}</span>
          </div>
        </div>
      </div>

      <!-- Voucher Body -->
      <div class="p-4 bg-white">
        <div class="row">
          <!-- Left: Expense Info -->
          <div class="col-md-6 mb-3">
            <div class="info-box p-3 h-100">
              <div class="d-flex align-items-center border-bottom pb-2 mb-3">
                <div class="icon-circle bg-blue-subtle text-primary mr-2">
                  <lucide-icon name="tag" style="width: 15px; height: 15px;" />
                </div>
                <h6 class="font-weight-bold text-dark mb-0">
                  {{ $t('Expense_Information') || 'Expense Information' }}
                </h6>
              </div>

              <div class="info-row">
                <span class="label text-muted">{{ $t('Expense_Category') || 'Category' }}</span>
                <span class="value font-weight-600 text-dark">
                  <span class="badge badge-light border px-2 py-1">{{ expense.category_name || '-' }}</span>
                </span>
              </div>
              <div class="info-row">
                <span class="label text-muted">{{ $t('Warehouse') || 'Warehouse / Branch' }}</span>
                <span class="value font-weight-600 text-dark">{{ expense.warehouse_name || '-' }}</span>
              </div>
              <div class="info-row">
                <span class="label text-muted">{{ $t('Created_By') || 'Created By' }}</span>
                <span class="value font-weight-600 text-dark">{{ expense.user_name || '-' }}</span>
              </div>
              <div class="info-row">
                <span class="label text-muted">{{ $t('Date') || 'Expense Date' }}</span>
                <span class="value font-weight-600 text-dark">{{ expense.date }}</span>
              </div>
            </div>
          </div>

          <!-- Right: Payment & Account Source -->
          <div class="col-md-6 mb-3">
            <div class="info-box p-3 h-100">
              <div class="d-flex align-items-center border-bottom pb-2 mb-3">
                <div class="icon-circle bg-green-subtle text-success mr-2">
                  <lucide-icon name="wallet" style="width: 15px; height: 15px;" />
                </div>
                <h6 class="font-weight-bold text-dark mb-0">
                  {{ $t('Payment_Account_Details') || 'Payment & Account Source' }}
                </h6>
              </div>

              <div class="info-row">
                <span class="label text-muted">{{ $t('Paid_Through_Account') || 'Paid From Account' }}</span>
                <span class="value">
                  <span class="badge badge-success-soft font-weight-bold px-2 py-1">
                    {{ expense.account_name || 'Cash in Hand' }}
                  </span>
                </span>
              </div>
              <div v-if="expense.account_num" class="info-row">
                <span class="label text-muted">{{ $t('Account_Number') || 'Account Code/No' }}</span>
                <span class="value font-family-monospace text-muted">{{ expense.account_num }}</span>
              </div>
              <div class="info-row">
                <span class="label text-muted">{{ $t('PaymentMethod') || 'Payment Method' }}</span>
                <span class="value font-weight-600 text-dark">
                  <span class="badge badge-light border px-2 py-1">{{ expense.payment_method || 'Cash' }}</span>
                </span>
              </div>
              <div class="info-row">
                <span class="label text-muted">{{ $t('Payment_Status') || 'Payment Status' }}</span>
                <span class="value">
                  <span class="badge badge-success text-white px-2 py-1 font-weight-normal">Settled</span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Description / Note -->
        <div class="mt-3 p-3 note-box">
          <div class="d-flex align-items-center mb-2">
            <lucide-icon name="file-text" style="width: 15px; height: 15px;" class="mr-1 text-muted" />
            <span class="font-weight-bold text-dark small text-uppercase" style="letter-spacing: 0.5px;">
              {{ $t('Details') || 'Description / Note' }}
            </span>
          </div>
          <p class="mb-0 text-dark" style="white-space: pre-wrap; font-size: 14px; line-height: 1.6;">
            {{ expense.details || 'No description provided.' }}
          </p>
        </div>

        <!-- Printable Signatures -->
        <div class="row mt-5 pt-4 d-none d-print-flex">
          <div class="col-4 text-center">
            <div style="border-top: 1px solid #111; padding-top: 6px;" class="small font-weight-bold">
              Prepared By
            </div>
          </div>
          <div class="col-4 text-center">
            <div style="border-top: 1px solid #111; padding-top: 6px;" class="small font-weight-bold">
              Accountant Signature
            </div>
          </div>
          <div class="col-4 text-center">
            <div style="border-top: 1px solid #111; padding-top: 6px;" class="small font-weight-bold">
              Authorized Signature
            </div>
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
  name: "DetailExpense",
  data() {
    return {
      loading: true,
      expenseId: null,
      expense: null,
      price_format_key: null
    };
  },
  created() {
    this.expenseId = this.$route.params.id;
    if (this.expenseId) {
      this.fetchExpense();
    } else {
      this.$router.push('/app/expenses/list');
    }
  },
  methods: {
    async fetchExpense() {
      this.loading = true;
      NProgress.start();
      NProgress.set(0.1);

      try {
        const { data } = await axios.get(`/expenses/${this.expenseId}`);
        if (data && data.expense) {
          this.expense = data.expense;
        }
      } catch (e) {
        this.$root.$bvToast.toast("Failed to load expense details", {
          title: "Error",
          variant: "danger",
          solid: true
        });
      } finally {
        this.loading = false;
        NProgress.done();
      }
    },
    printVoucher() {
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
    }
  }
};
</script>

<style scoped>
.clean-voucher-card {
  background: #ffffff !important;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.voucher-header {
  background-color: #ffffff !important;
}

.voucher-ref {
  font-size: 24px;
  letter-spacing: -0.3px;
}

.status-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  letter-spacing: 0.5px;
}

.status-paid {
  background-color: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.amount-pill {
  background: #ffffff;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: 12px 20px;
  text-align: right;
  min-width: 200px;
}

.amount-label {
  font-size: 11px;
  font-weight: 700;
  color: #991b1b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}

.amount-value {
  font-size: 24px;
  font-weight: 800;
  color: #dc2626;
  line-height: 1.1;
}

.info-box {
  background-color: #ffffff;
  border: 1px solid #edf2f7;
  border-radius: 10px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 7px 0;
  border-bottom: 1px dashed #f1f5f9;
  font-size: 13.5px;
}

.info-row:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.icon-circle {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.bg-blue-subtle {
  background-color: #eff6ff;
}

.bg-green-subtle {
  background-color: #ecfdf5;
}

.badge-success-soft {
  background-color: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
}

.note-box {
  background-color: #fafbfc;
  border: 1px solid #edf2f7;
  border-radius: 10px;
}

@media print {
  body * {
    visibility: hidden;
  }
  #expense-print-area, #expense-print-area * {
    visibility: visible;
  }
  #expense-print-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    border: none !important;
    box-shadow: none !important;
  }
}
</style>

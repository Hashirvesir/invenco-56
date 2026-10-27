<template>
  <!-- NEW FEATURE - SAFE ADDITION -->
  <div class="main-content">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-1">{{ $t('Trial_Balance_Title') }}</h4>
        <div class="text-muted small">{{ $t('Trial_Balance_Subtitle') }}</div>
      </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="row mb-3" v-if="!isLoading">
      <div class="col-md-4 mb-2">
        <div class="card p-3 shadow-sm border-0 border-left-success h-100">
          <div class="text-muted small text-uppercase font-weight-600">{{ $t('Total_Debit') || 'Total Debit' }}</div>
          <div class="h4 mb-0 text-success font-weight-bold mt-1">{{ toMoney(summary.grand_debit) }}</div>
        </div>
      </div>
      <div class="col-md-4 mb-2">
        <div class="card p-3 shadow-sm border-0 border-left-danger h-100">
          <div class="text-muted small text-uppercase font-weight-600">{{ $t('Total_Credit') || 'Total Credit' }}</div>
          <div class="h4 mb-0 text-danger font-weight-bold mt-1">{{ toMoney(summary.grand_credit) }}</div>
        </div>
      </div>
      <div class="col-md-4 mb-2">
        <div class="card p-3 shadow-sm border-0 h-100" :class="summary.is_balanced ? 'border-left-primary' : 'border-left-warning'">
          <div class="text-muted small text-uppercase font-weight-600">{{ $t('Status') || 'Trial Balance Status' }}</div>
          <div class="h5 mb-0 font-weight-bold mt-1" :class="summary.is_balanced ? 'text-primary' : 'text-danger'">
            <span v-if="summary.is_balanced">
              <lucide-icon name="check-circle" style="width: 18px; height: 18px; vertical-align: -2px;" /> Balanced (0.00)
            </span>
            <span v-else>
              Difference: {{ toMoney(summary.difference) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div class="card wrapper" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="tableRows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{ enabled: true, placeholder: $t('Search_this_table') }"
        :pagination-options="{ enabled: true, mode: 'records', nextLabel: $t('Next'), prevLabel: $t('Prev') }"
        styleClass="table-hover tableOne vgt-table cursor-pointer"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <div class="form-inline flex-wrap gap-2">
            <label class="mr-2 small text-muted">{{ $t('From') }}</label>
            <input v-model="filters.from" type="date" class="form-control mr-2"/>
            <label class="mr-2 small text-muted">{{ $t('To') }}</label>
            <input v-model="filters.to" type="date" class="form-control mr-2"/>
            <label class="mr-2 small text-muted">{{ $t('Type') }}</label>
            <select v-model="filters.type" class="form-control mr-2">
              <option value="">{{ $t('All') }}</option>
              <option value="asset">{{ $t('Asset') }}</option>
              <option value="liability">{{ $t('Liability') }}</option>
              <option value="equity">{{ $t('Equity') }}</option>
              <option value="income">{{ $t('Income') }}</option>
              <option value="expense">{{ $t('Expense') }}</option>
            </select>
            <b-button @click="Get_TB(1)" class="btn-rounded" variant="btn btn-outline-primary btn-icon m-1">
              <lucide-icon name="refresh-cw" />
              {{ $t('Apply') }}
            </b-button>
          </div>
        </div>

        <template slot="table-row" slot-scope="props">
          <template v-if="props.row && props.row.isTotal">
            <span v-if="props.column.field == 'code'"></span>
            <span v-else-if="props.column.field == 'name'" class="font-weight-bold d-block text-right">{{ $t('Total') }}</span>
            <span v-else-if="props.column.field == 'type'"></span>
            <span v-else-if="props.column.field == 'debit'" class="text-right d-block font-weight-bold text-success">{{ toMoney(summary.grand_debit || pageTotalDebit) }}</span>
            <span v-else-if="props.column.field == 'credit'" class="text-right d-block font-weight-bold text-danger">{{ toMoney(summary.grand_credit || pageTotalCredit) }}</span>
          </template>
          <template v-else>
            <span v-if="props.column.field == 'code'">
              <a @click.stop="$router.push({ name: 'accounting_v2_account_ledger', params: { id: props.row.coa_id } })" class="text-primary font-weight-bold cursor-pointer hover-underline" :title="'Open Account Ledger'">
                {{ props.row.code }}
              </a>
            </span>
            <span v-else-if="props.column.field == 'name'">
              <a @click.stop="$router.push({ name: 'accounting_v2_account_ledger', params: { id: props.row.coa_id } })" class="text-dark font-weight-600 cursor-pointer hover-underline" :title="'Open Account Ledger'">
                {{ props.row.name }}
              </a>
            </span>
            <span v-else-if="props.column.field == 'debit'" class="text-right d-block">{{ toMoney(props.row.debit) }}</span>
            <span v-else-if="props.column.field == 'credit'" class="text-right d-block">{{ toMoney(props.row.credit) }}</span>
          </template>
        </template>
      </vue-good-table>
    </div>
  </div>
</template>

<script>
import NProgress from "nprogress";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../../utils/priceFormat";

export default {
  name: "TrialBalanceV2",
  data() {
    return {
      isLoading: true,
      rows: [],
      totalRows: "",
      serverParams: {
        columnFilters: {},
        sort: { field: "code", type: "asc" },
        page: 1,
        perPage: 10
      },
      search: "",
      limit: "10",
      filters: { from: "", to: "", type: "" },
      summary: {
        grand_debit: 0,
        grand_credit: 0,
        difference: 0,
        is_balanced: true
      },
      price_format_key: null
    };
  },
  computed: {
    columns() {
      return [
        { label: this.$t('Code'), field: 'code', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Name'), field: 'name', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Type'), field: 'type', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('Debit'), field: 'debit', tdClass: 'text-right', thClass: 'text-right', sortable: true },
        { label: this.$t('Credit'), field: 'credit', tdClass: 'text-right', thClass: 'text-right', sortable: true },
      ];
    },
    tableRows() {
      if (!this.rows || !this.rows.length) return [];
      return [...this.rows, { isTotal: true }];
    },
    pageTotalDebit() { return this.rows.reduce((a,b)=>a+Number(b.debit||0),0); },
    pageTotalCredit() { return this.rows.reduce((a,b)=>a+Number(b.credit||0),0); }
  },
  created() { this.Get_TB(1); },
  methods: {
    updateParams(newProps) { this.serverParams = Object.assign({}, this.serverParams, newProps); },
    onPageChange({ currentPage }) { if (this.serverParams.page !== currentPage) { this.updateParams({ page: currentPage }); this.Get_TB(currentPage); } },
    onPerPageChange({ currentPerPage }) { if (this.limit !== currentPerPage) { this.limit = currentPerPage; this.updateParams({ page: 1, perPage: currentPerPage }); this.Get_TB(1); } },
    onSortChange(params) { if (!params || !params.length) return; this.updateParams({ sort: { type: params[0].type, field: params[0].field } }); this.Get_TB(this.serverParams.page); },
    onSearch(value) { this.search = value.searchTerm; this.Get_TB(this.serverParams.page); },
    async Get_TB(page) {
      NProgress.start(); NProgress.set(0.1);
      axios.get(
        "/accounting/v2/reports/trial-balance?page=" + page +
        "&SortField=" + this.serverParams.sort.field +
        "&SortType=" + this.serverParams.sort.type +
        "&search=" + this.search +
        "&limit=" + this.limit +
        "&from=" + (this.filters.from || "") +
        "&to=" + (this.filters.to || "") +
        "&type=" + (this.filters.type || "")
      )
      .then(({data}) => {
        this.rows = (data && (data.data || [])) || [];
        this.totalRows = (data && (data.totalRows ?? data.total ?? this.rows.length)) || 0;
        if (data) {
          this.summary = {
            grand_debit: data.grand_debit || 0,
            grand_credit: data.grand_credit || 0,
            difference: data.difference || 0,
            is_balanced: data.is_balanced !== undefined ? data.is_balanced : true
          };
        }
        NProgress.done(); this.isLoading = false;
      })
      .catch(() => { NProgress.done(); this.isLoading = false; });
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
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #10b981 !important; }
.border-left-danger { border-left: 4px solid #ef4444 !important; }
.border-left-warning { border-left: 4px solid #f59e0b !important; }
.cursor-pointer { cursor: pointer; }
.hover-underline:hover { text-decoration: underline !important; }
</style>

        const n = parseFloat(v || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, 2, effectiveKey);
      } catch (e) {
        const n = parseFloat(v || 0);
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
    }
  }
};
</script>

<style scoped>
</style>




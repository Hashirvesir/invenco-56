



<template>
  <div class="main-content">
    <breadcumb :page="$t('MarginReport')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <!-- Date Range + Filters Row -->
    <b-row class="mb-3" v-if="!isLoading">
      <b-col md="3" sm="12">
        <b-form-group :label="$t('StartDate')">
          <b-form-input type="date" v-model="filter.from" @change="loadData(1)" />
        </b-form-group>
      </b-col>
      <b-col md="3" sm="12">
        <b-form-group :label="$t('EndDate')">
          <b-form-input type="date" v-model="filter.to" @change="loadData(1)" />
        </b-form-group>
      </b-col>
      <b-col md="3" sm="12">
        <b-form-group :label="$t('warehouse')">
          <v-select
            v-model="filter.warehouse_id"
            :reduce="o => o.value"
            :placeholder="$t('Choose_Warehouse')"
            :options="warehouses.map(w => ({ label: w.name, value: w.id }))"
            @input="loadData(1)"
          />
        </b-form-group>
      </b-col>
      <b-col md="3" sm="12">
        <b-form-group :label="$t('Product')">
          <b-form-input
            v-model="filter.search"
            :placeholder="$t('Search_Product')"
            @input="onSearchInput"
          />
        </b-form-group>
      </b-col>
    </b-row>

    <!-- Summary Cards -->
    <b-row class="mb-3" v-if="!isLoading">
      <b-col md="3" sm="6">
        <div class="summary-card bg-primary">
          <div class="summary-label">{{ $t('TotalSales') }}</div>
          <div class="summary-value">{{ formatPrice(summary.total_sales) }}</div>
        </div>
      </b-col>
      <b-col md="3" sm="6">
        <div class="summary-card bg-warning">
          <div class="summary-label">{{ $t('TotalCost') }}</div>
          <div class="summary-value">{{ formatPrice(summary.total_cost) }}</div>
        </div>
      </b-col>
      <b-col md="3" sm="6">
        <div class="summary-card bg-success">
          <div class="summary-label">{{ $t('GrossProfit') }}</div>
          <div class="summary-value">{{ formatPrice(summary.gross_profit) }}</div>
        </div>
      </b-col>
      <b-col md="3" sm="6">
        <div class="summary-card bg-info">
          <div class="summary-label">{{ $t('AverageMargin') }}</div>
          <div class="summary-value">{{ summary.avg_margin }}%</div>
        </div>
      </b-col>
    </b-row>

    <b-card v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :rows="rows"
        :totalRows="totalRows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        :pagination-options="{ enabled: true, mode: 'records', nextLabel: 'next', prevLabel: 'prev' }"
        :styleClass="'order-table vgt-table'"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="exportPDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
          <vue-excel-xlsx
            class="btn btn-sm btn-outline-danger ripple m-1"
            :data="rows"
            :columns="columns"
            :file-name="'margin_report'"
            :file-type="'xlsx'"
            :sheet-name="'margin_report'"
          >
            <lucide-icon name="file-spreadsheet" /> EXCEL
          </vue-excel-xlsx>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'total_cost_amount'">
            {{ formatPrice(props.row.total_cost_amount) }}
          </span>
          <span v-else-if="props.column.field === 'total_sale_amount'">
            {{ formatPrice(props.row.total_sale_amount) }}
          </span>
          <span v-else-if="props.column.field === 'gross_profit'">
            <span :class="props.row.gross_profit >= 0 ? 'text-success font-weight-bold' : 'text-danger font-weight-bold'">
              {{ formatPrice(props.row.gross_profit) }}
            </span>
          </span>
          <span v-else-if="props.column.field === 'margin_pct'">
            <span :class="marginClass(props.row.margin_pct)">
              {{ props.row.margin_pct }}%
            </span>
          </span>
          <span v-else>{{ props.formattedRow[props.column.field] }}</span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>

<script>
import NProgress from 'nprogress';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import moment from 'moment';
import { mapGetters } from 'vuex';

export default {
  metaInfo: { title: 'Margin Report' },

  data() {
    return {
      isLoading: true,
      totalRows: 0,
      rows: [],
      warehouses: [],
      summary: { total_sales: 0, total_cost: 0, gross_profit: 0, avg_margin: 0 },
      serverParams: { page: 1, perPage: 10, sortField: 'total_sale_amount', sortType: 'desc' },
      filter: {
        from: moment().subtract(29, 'days').format('YYYY-MM-DD'),
        to: moment().format('YYYY-MM-DD'),
        warehouse_id: '',
        search: '',
      },
      searchTimer: null,
    };
  },

  computed: {
    ...mapGetters(['currentUser']),
    columns() {
      return [
        { label: this.$t('ProductName'),        field: 'product_name',      tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('warehouse'),           field: 'warehouse_name',    tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('QuantitySold'),        field: 'quantity_sold',     tdClass: 'text-left', thClass: 'text-left', type: 'number' },
        { label: this.$t('CostPerUnit'),         field: 'cost_per_unit',     tdClass: 'text-left', thClass: 'text-left', type: 'number' },
        { label: this.$t('TotalCostAmount'),     field: 'total_cost_amount', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('TotalSaleAmount'),     field: 'total_sale_amount', tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('GrossProfit'),         field: 'gross_profit',      tdClass: 'text-left', thClass: 'text-left' },
        { label: this.$t('MarginPercent'),       field: 'margin_pct',        tdClass: 'text-left', thClass: 'text-left' },
      ];
    },
  },

  methods: {
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.serverParams.page = currentPage;
        this.loadData(currentPage);
      }
    },
    onPerPageChange({ currentPerPage }) {
      this.serverParams.perPage = currentPerPage;
      this.serverParams.page = 1;
      this.loadData(1);
    },
    onSortChange(params) {
      this.serverParams.sortField = params[0].field;
      this.serverParams.sortType  = params[0].type;
      this.loadData(this.serverParams.page);
    },
    onSearchInput() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.loadData(1), 400);
    },

    loadData(page) {
      NProgress.start();
      const p = this.serverParams;
      const f = this.filter;
      axios.get('/report/margin_report', {
        params: {
          page,
          limit:       p.perPage,
          SortField:   p.sortField,
          SortType:    p.sortType,
          from:        f.from,
          to:          f.to,
          warehouse_id: f.warehouse_id || '',
          search:      f.search || '',
        },
      }).then(res => {
        this.rows       = res.data.data;
        this.totalRows  = res.data.totalRows;
        this.warehouses = res.data.warehouses;
        this.summary    = res.data.summary;
        NProgress.done();
        this.isLoading = false;
      }).catch(() => {
        NProgress.done();
        this.isLoading = false;
      });
    },

    formatPrice(val) {
      const sym = this.currentUser && this.currentUser.currency ? this.currentUser.currency + ' ' : '';
      return sym + parseFloat(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    marginClass(pct) {
      if (pct >= 30) return 'badge badge-outline-success';
      if (pct >= 10) return 'badge badge-outline-warning';
      return 'badge badge-outline-danger';
    },

    exportPDF() {
      const pdf = new jsPDF('l', 'pt');
      const fontPath = '/fonts/Vazirmatn-Bold.ttf';
      try {
        pdf.addFont(fontPath, 'Vazirmatn', 'normal');
        pdf.addFont(fontPath, 'Vazirmatn', 'bold');
      } catch (e) {}
      pdf.setFont('Vazirmatn', 'normal');

      const marginX = 40;
      const rtl = (this.$i18n && ['ar', 'fa', 'ur', 'he'].includes(this.$i18n.locale)) ||
                  (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      // Build applied filters list
      const warehouseObj = this.warehouses.find(w => w.id == this.filter.warehouse_id);
      const filterItems = [];
      if (this.filter.from && this.filter.to)
        filterItems.push({ label: 'Date', value: `${this.filter.from}  —  ${this.filter.to}` });
      if (warehouseObj)
        filterItems.push({ label: 'Warehouse', value: warehouseObj.name });
      if (this.filter.search)
        filterItems.push({ label: 'Product', value: this.filter.search });
      const hasFilters = filterItems.length > 0;

      // Table body
      const body = this.rows.map(r => [
        r.product_name,
        r.warehouse_name,
        parseFloat(r.quantity_sold || 0).toFixed(2),
        parseFloat(r.cost_per_unit || 0).toFixed(2),
        parseFloat(r.total_cost_amount || 0).toFixed(2),
        parseFloat(r.total_sale_amount || 0).toFixed(2),
        parseFloat(r.gross_profit || 0).toFixed(2),
        parseFloat(r.margin_pct || 0).toFixed(2) + '%',
      ]);

      const s = this.summary;
      const foot = [[
        this.$t('Total') || 'Total', '', '',  '',
        parseFloat(s.total_cost || 0).toFixed(2),
        parseFloat(s.total_sales || 0).toFixed(2),
        parseFloat(s.gross_profit || 0).toFixed(2),
        parseFloat(s.avg_margin || 0).toFixed(2) + '%',
      ]];

      const headers = this.columns.map(c => c.label);

      autoTable(pdf, {
        head: [headers],
        body,
        foot,
        startY: hasFilters ? 148 : 110,
        theme: 'striped',
        margin: { left: marginX, right: marginX },
        styles:          { font: 'Vazirmatn', fontSize: 8, cellPadding: 4, halign: rtl ? 'right' : 'left', textColor: 33 },
        headStyles:      { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [26, 86, 219], textColor: 255 },
        alternateRowStyles: { fillColor: [245, 247, 250] },
        footStyles:      { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [26, 86, 219], textColor: 255 },
        didDrawPage: (d) => {
          const pageW = pdf.internal.pageSize.getWidth();
          const pageH = pdf.internal.pageSize.getHeight();

          // Blue banner
          pdf.setFillColor(26, 86, 219);
          pdf.rect(0, 0, pageW, 60, 'F');

          // Title
          pdf.setTextColor(255);
          pdf.setFont('Vazirmatn', 'bold');
          pdf.setFontSize(16);
          const title = this.$t('MarginReport') || 'Margin Report';
          rtl ? pdf.text(title, pageW - marginX, 38, { align: 'right' })
              : pdf.text(title, marginX, 38);
          pdf.setTextColor(33);

          // Filter summary box
          if (hasFilters) {
            const lineH = 13;
            const maxW = pageW - 2 * marginX;
            const filterText = filterItems.map(f => `${f.label}: ${f.value}`).join('   |   ');
            const lines = pdf.splitTextToSize(filterText, maxW);
            const displayLines = lines.slice(0, 3);
            const filterBoxH = 8 + displayLines.length * lineH + 6;

            pdf.setFillColor(235, 242, 255);
            pdf.rect(0, 62, pageW, filterBoxH, 'F');
            pdf.setFont('Vazirmatn', 'bold');
            pdf.setFontSize(7);
            pdf.setTextColor(26, 86, 219);
            rtl ? pdf.text('Applied Filters:', pageW - marginX, 72, { align: 'right' })
                : pdf.text('Applied Filters:', marginX, 72);
            pdf.setFont('Vazirmatn', 'normal');
            pdf.setFontSize(8.5);
            pdf.setTextColor(50, 50, 50);
            displayLines.forEach((line, i) => {
              rtl ? pdf.text(line, pageW - marginX, 83 + i * lineH, { align: 'right' })
                  : pdf.text(line, marginX, 83 + i * lineH);
            });
            pdf.setTextColor(33);
          }

          // Summary box
          const sumY = hasFilters ? 108 : 70;
          const currency = (this.currentUser && this.currentUser.currency) ? this.currentUser.currency + ' ' : '';
          const summaryItems = [
            `Total Sales: ${currency}${parseFloat(s.total_sales || 0).toFixed(2)}`,
            `Total Cost: ${currency}${parseFloat(s.total_cost || 0).toFixed(2)}`,
            `Gross Profit: ${currency}${parseFloat(s.gross_profit || 0).toFixed(2)}`,
            `Avg Margin: ${parseFloat(s.avg_margin || 0).toFixed(2)}%`,
          ];
          pdf.setFillColor(245, 247, 250);
          pdf.rect(0, sumY, pageW, 34, 'F');
          pdf.setFont('Vazirmatn', 'normal');
          pdf.setFontSize(8.5);
          pdf.setTextColor(33);
          const colW = (pageW - 2 * marginX) / summaryItems.length;
          summaryItems.forEach((txt, i) => {
            const x = marginX + i * colW;
            pdf.text(txt, x, sumY + 20);
          });

          // Page numbers
          pdf.setFontSize(8);
          const pn = `${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`;
          rtl ? pdf.text(pn, marginX, pageH - 14, { align: 'left' })
              : pdf.text(pn, pageW - marginX, pageH - 14, { align: 'right' });
        },
      });

      pdf.save('Margin_Report.pdf');
    },
  },

  created() {
    this.loadData(1);
  },
};
</script>

<style scoped>
.summary-card {
  color: #fff !important;
  border-radius: 8px;
  padding: 16px 20px;
  margin-bottom: 12px;
}
.summary-card * { color: #fff !important; }
.summary-label { font-size: 12px; opacity: 0.85; margin-bottom: 4px; }
.summary-value { font-size: 22px; font-weight: 700; }
</style>

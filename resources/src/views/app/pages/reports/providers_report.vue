<template>
  <div class="main-content">
    <breadcumb :page="$t('SuppliersReport')" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <!-- Date Range Filter -->
    <b-col md="12" class="text-center mb-3" v-if="!isLoading">
      <date-range-picker
        v-model="dateRange"
        :startDate="startDate"
        :endDate="endDate"
        :time-picker="false"
        :autoApply="true"
        :showDropdowns="true"
        :opens="'right'"
        :drops="'down'"
        @update="Submit_filter_dateRange"
        :locale-data="locale"
      >
        <template v-slot:input="picker" style="min-width: 350px;">
          {{ formatDateTime(picker.startDate) }} — {{ formatDateTime(picker.endDate) }}
        </template>
      </date-range-picker>
    </b-col>

    <b-card class="wrapper print-table-only" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        :group-options="{
          enabled: true,
          headerPosition: 'bottom',
        }"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
          placeholder: $t('Search_this_table'),
          enabled: true,
        }"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          nextLabel: 'next',
          prevLabel: 'prev',
        }"
        styleClass="tableOne table-hover vgt-table mt-4"
      >
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="Providers_PDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
        </div>
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <router-link title="Report" :to="'/app/reports/detail_supplier/'+props.row.id">
              <lucide-icon class="text-25 text-info" name="eye" />
            </router-link>
          </span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>


<script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import { mapGetters } from "vuex";
import DateRangePicker from "vue2-daterange-picker";
import moment from "moment";

export default {
  metaInfo: {
    title: "Report Providers"
  },
  components: { DateRangePicker },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: { field: "id", type: "desc" },
        page: 1,
        perPage: 10
      },
      limit: "10",
      search: "",
      totalRows: "",
      startDate: "",
      endDate: "",
      dateRange: { startDate: "", endDate: "" },
      locale: {
        Label: "Apply",
        cancelLabel: "Cancel",
        weekLabel: "W",
        customRangeLabel: "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1,
      },
      providers: [],
      provider: {},
      rows: [{
        total_purchase: "Total",
        children: [],
      }],
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    columns() {
      return [
        { label: this.$t("SupplierName"),            field: "name",           tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Phone"),                   field: "phone",          tdClass: "text-left", thClass: "text-left" },
        { label: this.$t("Purchases"),               field: "total_purchase", tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("TotalAmount"),             field: "total_amount",   type: "decimal", headerField: this.sumCount,  tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Paid"),                    field: "total_paid",     type: "decimal", headerField: this.sumCount2, tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Total_Purchase_Due"),      field: "due",            type: "decimal", headerField: this.sumCount3, tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Total_Purchase_Return_Due"), field: "return_Due",   type: "decimal", headerField: this.sumCount4, tdClass: "text-left", thClass: "text-left", sortable: false },
        { label: this.$t("Action"),                  field: "actions",        tdClass: "text-left", thClass: "text-left", sortable: false },
      ];
    }
  },

  methods: {

    sumCount(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) return 0;
      return rowObj.children.reduce((s, r) => s + (typeof r.total_amount === "number" ? r.total_amount : 0), 0);
    },
    sumCount2(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) return 0;
      return rowObj.children.reduce((s, r) => s + (typeof r.total_paid === "number" ? r.total_paid : 0), 0);
    },
    sumCount3(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) return 0;
      return rowObj.children.reduce((s, r) => s + (typeof r.due === "number" ? r.due : 0), 0);
    },
    sumCount4(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) return 0;
      return rowObj.children.reduce((s, r) => s + (typeof r.return_Due === "number" ? r.return_Due : 0), 0);
    },

    //--------------------------- Providers_PDF (frontend jsPDF) -------------------------------\\
    Providers_PDF() {
      const self = this;
      const pdf = new jsPDF("l", "pt");
      try {
        pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "normal");
        pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "bold");
      } catch (e) {}
      pdf.setFont("Vazirmatn", "normal");

      const marginX = 40;
      const rtl = (self.$i18n && ["ar", "fa", "ur", "he"].includes(self.$i18n.locale)) ||
                  (typeof document !== "undefined" && document.documentElement.dir === "rtl");

      // Build filter items
      const filterItems = [];
      if (self.startDate && self.endDate)
        filterItems.push({ label: "Date", value: `${self.startDate}  —  ${self.endDate}` });
      const hasFilters = filterItems.length > 0;

      // Table data
      const data = self.rows[0].children || [];
      const body = data.map(r => [
        r.name || "",
        r.phone || "",
        r.total_purchase || 0,
        parseFloat(r.total_amount || 0).toFixed(2),
        parseFloat(r.total_paid || 0).toFixed(2),
        parseFloat(r.due || 0).toFixed(2),
        parseFloat(r.return_Due || 0).toFixed(2),
      ]);

      const totalAmount   = self.sumCount(self.rows[0]);
      const totalPaid     = self.sumCount2(self.rows[0]);
      const totalDue      = self.sumCount3(self.rows[0]);
      const totalReturnDue = self.sumCount4(self.rows[0]);

      const foot = [[
        self.$t("Total") || "Total", "", "",
        totalAmount.toFixed(2),
        totalPaid.toFixed(2),
        totalDue.toFixed(2),
        totalReturnDue.toFixed(2),
      ]];

      const headers = [
        self.$t("SupplierName"),
        self.$t("Phone"),
        self.$t("Purchases"),
        self.$t("TotalAmount"),
        self.$t("Paid"),
        self.$t("Total_Purchase_Due"),
        self.$t("Total_Purchase_Return_Due"),
      ];

      autoTable(pdf, {
        head: [headers],
        body,
        foot,
        startY: hasFilters ? 125 : 90,
        theme: "striped",
        margin: { left: marginX, right: marginX },
        styles:             { font: "Vazirmatn", fontSize: 8, cellPadding: 4, halign: rtl ? "right" : "left", textColor: 33 },
        headStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
        alternateRowStyles: { fillColor: [245, 247, 250] },
        footStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
        didDrawPage(d) {
          const pageW = pdf.internal.pageSize.getWidth();
          const pageH = pdf.internal.pageSize.getHeight();

          // Blue banner
          pdf.setFillColor(26, 86, 219);
          pdf.rect(0, 0, pageW, 60, "F");

          // Title
          pdf.setTextColor(255);
          pdf.setFont("Vazirmatn", "bold");
          pdf.setFontSize(16);
          const title = self.$t("SuppliersReport") || "Suppliers Report";
          rtl ? pdf.text(title, pageW - marginX, 38, { align: "right" })
              : pdf.text(title, marginX, 38);

          // Date range subtitle in banner
          if (self.startDate && self.endDate) {
            pdf.setFontSize(9);
            pdf.setFont("Vazirmatn", "normal");
            const sub = `${self.startDate}  —  ${self.endDate}`;
            rtl ? pdf.text(sub, pageW - marginX, 52, { align: "right" })
                : pdf.text(sub, marginX, 52);
          }
          pdf.setTextColor(33);

          // Filter box
          if (hasFilters) {
            const lineH = 13;
            const maxW = pageW - 2 * marginX;
            const filterText = filterItems.map(f => `${f.label}: ${f.value}`).join("   |   ");
            const lines = pdf.splitTextToSize(filterText, maxW).slice(0, 3);
            const boxH = 8 + lines.length * lineH + 6;

            pdf.setFillColor(235, 242, 255);
            pdf.rect(0, 62, pageW, boxH, "F");
            pdf.setFont("Vazirmatn", "bold");
            pdf.setFontSize(7);
            pdf.setTextColor(26, 86, 219);
            rtl ? pdf.text("Applied Filters:", pageW - marginX, 72, { align: "right" })
                : pdf.text("Applied Filters:", marginX, 72);
            pdf.setFont("Vazirmatn", "normal");
            pdf.setFontSize(8.5);
            pdf.setTextColor(50, 50, 50);
            lines.forEach((line, i) => {
              rtl ? pdf.text(line, pageW - marginX, 83 + i * lineH, { align: "right" })
                  : pdf.text(line, marginX, 83 + i * lineH);
            });
            pdf.setTextColor(33);
          }

          // Page numbers
          pdf.setFontSize(8);
          pdf.setTextColor(100);
          const pn = `${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`;
          rtl ? pdf.text(pn, marginX, pageH - 14, { align: "left" })
              : pdf.text(pn, pageW - marginX, pageH - 14, { align: "right" });
        },
      });

      const currency = (self.currentUser && self.currentUser.currency) ? self.currentUser.currency + " " : "";
      pdf.save(`Suppliers_Report.pdf`);
    },

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Provider_Report(currentPage);
      }
    },

    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Provider_Report(1);
      }
    },

    onSortChange(params) {
      this.updateParams({ sort: { type: params[0].type, field: params[0].field } });
      this.Get_Provider_Report(this.serverParams.page);
    },

    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Provider_Report(this.serverParams.page);
    },

    formatNumber(number, dec) {
      const value = (typeof number === "string" ? number : number.toString()).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec) return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    printTableOnly() {
      const root = this.$el;
      if (!root) { window.print(); return; }
      const tableCard = root.querySelector(".print-table-only");
      if (!tableCard) { window.print(); return; }

      const providersData = Array.isArray(this.rows[0]?.children) && this.rows[0].children.length > 0
        ? this.rows[0].children
        : (this.providers || []);

      let tableHtml = `<table class="vgt-table table table-hover tableOne">`;
      tableHtml += `<thead><tr>`;
      this.columns.filter(col => col.field !== "actions").forEach(col => {
        tableHtml += `<th class="text-left">${col.label}</th>`;
      });
      tableHtml += `</tr></thead><tbody>`;
      providersData.forEach(row => {
        tableHtml += `<tr>`;
        this.columns.filter(col => col.field !== "actions").forEach(col => {
          let cellContent = row[col.field];
          if (["total_amount", "total_paid", "due", "return_Due"].includes(col.field))
            cellContent = this.formatNumber(row[col.field], 2);
          tableHtml += `<td class="text-left">${cellContent || ""}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      const totalAmount   = this.sumCount(this.rows[0]);
      const totalPaid     = this.sumCount2(this.rows[0]);
      const totalDue      = this.sumCount3(this.rows[0]);
      const totalReturnDue = this.sumCount4(this.rows[0]);

      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.$t("Total")}</td>`;
      tableHtml += `<td colspan="2"></td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.formatNumber(totalAmount, 2)}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.formatNumber(totalPaid, 2)}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.formatNumber(totalDue, 2)}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.formatNumber(totalReturnDue, 2)}</td>`;
      tableHtml += `</tr></tfoot></table>`;

      const w = window.open("", "_blank");
      if (!w) { window.print(); return; }
      const title = `${this.$t("Reports")} / ${this.$t("SuppliersReport")}`;
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l => l.outerHTML).join("\n");
      const inlineStyles = Array.from(document.querySelectorAll("style")).filter(s => !((s.textContent || "").includes("@media print"))).map(s => s.outerHTML).join("\n");
      const doc = w.document;
      doc.open();
      doc.write(`<!doctype html><html><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><base href="${window.location.origin}/" /><title>${title}</title>${links}${inlineStyles}<style>@media print { body, body * { visibility: visible !important; } } body { margin: 0.3cm; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style></head><body><div style="font-weight:600;margin-bottom:8px;">${title}</div>${tableHtml}</body></html>`);
      doc.close();
      w.focus();
      setTimeout(() => { w.print(); w.close(); }, 400);
    },

    Submit_filter_dateRange() {
      const start = this.dateRange.startDate ? moment(this.dateRange.startDate) : null;
      const end   = this.dateRange.endDate   ? moment(this.dateRange.endDate)   : null;
      if (start && end) {
        this.startDate = start.format("YYYY-MM-DD");
        this.endDate   = end.format("YYYY-MM-DD");
      }
      this.Get_Provider_Report(1);
    },

    formatDateTime(date) {
      return date ? moment(date).format("YYYY-MM-DD") : "";
    },

    //--------------------------- Get Provider Report -------------\\
    Get_Provider_Report(page) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("report/provider", {
          params: {
            page,
            SortField: this.serverParams.sort.field,
            SortType:  this.serverParams.sort.type,
            search:    this.search,
            limit:     this.limit,
            from:      this.startDate || "",
            to:        this.endDate   || "",
          }
        })
        .then(response => {
          this.providers = response.data.report;
          this.totalRows = response.data.totalRows;
          this.rows[0].children = this.providers;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => { this.isLoading = false; }, 500);
        });
    }
  },

  created() {
    this.Get_Provider_Report(1);
  }
};
</script>
